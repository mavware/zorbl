<?php

namespace App\Listeners;

use App\Models\StripeWebhookLog;
use App\Models\User;
use App\Notifications\ProSubscriptionStarted;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionPaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;
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

        $object = $payload['data']['object'] ?? [];
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
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($user),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($user, $object),
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

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
