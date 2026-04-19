<?php

namespace App\Listeners;

use App\Models\StripeWebhookLog;
use App\Models\User;
use App\Notifications\ProSubscriptionStarted;
use App\Notifications\SubscriptionDisputeAlert;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionPlanChanged;
use App\Notifications\SubscriptionRefunded;
use App\Notifications\SubscriptionRenewed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Throwable;

class HandleStripeWebhook implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $eventId = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;

        if ($eventId === null || $type === null) {
            return;
        }

        $data = $payload['data'] ?? [];
        $object = $data['object'] ?? [];
        $previous = $data['previous_attributes'] ?? [];
        $customerId = $this->extractCustomerId($object);
        $user = $customerId !== null ? Cashier::findBillable($customerId) : null;

        try {
            $log = StripeWebhookLog::create([
                'stripe_event_id' => $eventId,
                'type' => $type,
                'livemode' => (bool) ($payload['livemode'] ?? false),
                'user_id' => $user?->id,
                'stripe_customer_id' => $customerId,
                'payload' => $payload,
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return;
            }

            throw $e;
        }

        try {
            match ($type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($user, $object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($user, $object, $previous),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($user),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($user, $object),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($user, $object),
                'charge.refunded' => $this->handleChargeRefunded($user, $object),
                'charge.dispute.created' => $this->handleChargeDisputeCreated($user, $object),
                default => null,
            };

            $log->update(['processed_at' => now()]);
        } catch (Throwable $e) {
            Log::error('Stripe webhook side-effect failed', [
                'event_id' => $eventId,
                'type' => $type,
                'exception' => $e->getMessage(),
            ]);

            $log->update(['error' => $e->getMessage()]);

            throw $e;
        }
    }

    private function handleSubscriptionCreated(?User $user, array $object): void
    {
        if ($user === null) {
            return;
        }

        $status = $object['status'] ?? null;

        if (! in_array($status, ['active', 'trialing'], true)) {
            return;
        }

        $user->notify(new ProSubscriptionStarted);
    }

    private function handleSubscriptionUpdated(?User $user, array $object, array $previous): void
    {
        if ($user === null) {
            return;
        }

        $previousPriceId = $this->extractPriceId($previous);
        $newPriceId = $this->extractPriceId($object);

        if ($previousPriceId === null || $newPriceId === null || $previousPriceId === $newPriceId) {
            return;
        }

        $user->notify(new SubscriptionPlanChanged(
            fromPlan: $this->planLabel($previousPriceId),
            toPlan: $this->planLabel($newPriceId),
        ));
    }

    private function handleSubscriptionDeleted(?User $user): void
    {
        if ($user === null) {
            return;
        }

        $user->notify(new SubscriptionEnded);
    }

    private function handleInvoicePaymentFailed(?User $user, array $object): void
    {
        if ($user === null) {
            return;
        }

        if (($object['billing_reason'] ?? null) === 'subscription_create') {
            return;
        }

        $user->notify(new SubscriptionPaymentFailed(
            hostedInvoiceUrl: $object['hosted_invoice_url'] ?? null,
        ));
    }

    private function handleInvoicePaymentSucceeded(?User $user, array $object): void
    {
        if ($user === null) {
            return;
        }

        if (($object['billing_reason'] ?? null) !== 'subscription_cycle') {
            return;
        }

        $user->notify(new SubscriptionRenewed(
            amount: (int) ($object['amount_paid'] ?? 0),
            currency: (string) ($object['currency'] ?? 'usd'),
            hostedInvoiceUrl: $object['hosted_invoice_url'] ?? null,
        ));
    }

    private function handleChargeRefunded(?User $user, array $object): void
    {
        if ($user === null) {
            return;
        }

        $amountRefunded = (int) ($object['amount_refunded'] ?? 0);

        if ($amountRefunded <= 0) {
            return;
        }

        $user->notify(new SubscriptionRefunded(
            amountRefunded: $amountRefunded,
            currency: (string) ($object['currency'] ?? 'usd'),
            fullRefund: (bool) ($object['refunded'] ?? false),
        ));
    }

    private function handleChargeDisputeCreated(?User $user, array $object): void
    {
        try {
            $admins = User::role('Admin')->get();
        } catch (RoleDoesNotExist) {
            return;
        }

        if ($admins->isEmpty()) {
            return;
        }

        $admins->each->notify(new SubscriptionDisputeAlert(
            disputeId: (string) ($object['id'] ?? ''),
            amount: (int) ($object['amount'] ?? 0),
            currency: (string) ($object['currency'] ?? 'usd'),
            reason: isset($object['reason']) ? (string) $object['reason'] : null,
            customerUserId: $user?->id,
            customerEmail: $user?->email,
        ));
    }

    private function extractCustomerId(array $object): ?string
    {
        $candidate = $object['customer'] ?? null;

        if (is_string($candidate)) {
            return $candidate;
        }

        if (is_array($candidate)) {
            return $candidate['id'] ?? null;
        }

        if (($object['object'] ?? null) === 'customer') {
            return $object['id'] ?? null;
        }

        return null;
    }

    private function extractPriceId(array $subscriptionData): ?string
    {
        $items = $subscriptionData['items']['data'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        $priceId = $items[0]['price']['id'] ?? null;

        return is_string($priceId) ? $priceId : null;
    }

    private function planLabel(string $priceId): string
    {
        return match ($priceId) {
            (string) config('services.stripe.pro_monthly_price') => __('Pro Monthly'),
            (string) config('services.stripe.pro_yearly_price') => __('Pro Yearly'),
            default => $priceId,
        };
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
