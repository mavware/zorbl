<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => fake()->url(),
            'description' => fake()->sentence(),
            'secret' => Str::random(32),
            'events' => [WebhookEvent::PuzzleCompleted->value],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * @param  array<int, WebhookEvent>  $events
     */
    public function forEvents(array $events): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => array_map(fn (WebhookEvent $e) => $e->value, $events),
        ]);
    }
}
