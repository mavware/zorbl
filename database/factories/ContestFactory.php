<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contest>
 */
class ContestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(5),
            'description' => fake()->paragraph(),
            'rules' => fake()->paragraph(),
            'meta_answer' => strtoupper(fake()->word()),
            'meta_hint' => fake()->sentence(),
            'status' => 'draft',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(8),
            'max_meta_attempts' => 0,
            'is_featured' => false,
        ];
    }

    /**
     * Contest that is currently active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
        ]);
    }

    /**
     * Contest that hasn't started yet.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'upcoming',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(9),
        ]);
    }

    /**
     * Contest that has already ended.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'starts_at' => now()->subDays(8),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Contest in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Featured contest.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
