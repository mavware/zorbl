<?php

namespace Database\Factories;

use App\Models\StripeWebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StripeWebhookLog>
 */
class StripeWebhookLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventId = 'evt_'.Str::random(24);
        $type = fake()->randomElement([
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
        ]);

        return [
            'stripe_event_id' => $eventId,
            'type' => $type,
            'livemode' => false,
            'user_id' => null,
            'stripe_customer_id' => 'cus_'.Str::random(14),
            'payload' => [
                'id' => $eventId,
                'type' => $type,
                'livemode' => false,
                'data' => ['object' => []],
            ],
            'processed_at' => now(),
            'error' => null,
        ];
    }
}
