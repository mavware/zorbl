<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAccount
{
    /**
     * Hard-delete a user and all the personal data we hold for them. Cascading
     * foreign keys handle most relations; this action covers the pieces that
     * don't cascade (Sanctum tokens, notifications) and the side effects that
     * have to happen outside the database (Stripe subscription cancellation).
     */
    public function __invoke(User $user): void
    {
        $this->cancelStripeSubscriptionsSafely($user);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->notifications()->delete();

            // Cascading FKs handle crosswords, attempts, likes, comments, clues,
            // contests, support tickets, achievements, AI usage, blocked tags,
            // webhook endpoints, cookie consents, favorites.
            $user->delete();
        });
    }

    /**
     * Cancel any active Cashier subscription so Stripe stops billing the customer.
     * Wrapped in try/catch because Stripe failures must not block account deletion —
     * the user's right to erasure outranks our ability to reach Stripe.
     */
    private function cancelStripeSubscriptionsSafely(User $user): void
    {
        try {
            foreach ($user->subscriptions as $subscription) {
                if ($subscription->active() || $subscription->onTrial() || $subscription->onGracePeriod()) {
                    $subscription->cancelNow();
                }
            }
        } catch (Throwable $e) {
            Log::warning('Stripe cancellation failed during account deletion', [
                'user_id' => $user->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
