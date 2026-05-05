<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => WebhookEvent::PuzzleCompleted->value,
            'payload' => ['event' => 'puzzle.completed', 'data' => []],
            'response_code' => 200,
            'success' => true,
            'attempt_count' => 1,
            'delivered_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_code' => 500,
            'success' => false,
            'response_body' => 'Internal Server Error',
        ]);
    }
}
