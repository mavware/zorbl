<?php

namespace Database\Factories;

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuzzleAttempt>
 */
class PuzzleAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'crossword_id' => Crossword::factory(),
            'progress' => Crossword::emptySolution(15, 15),
            'is_completed' => false,
        ];
    }

    /**
     * Mark the attempt as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'solve_time_seconds' => fake()->numberBetween(60, 3600),
        ]);
    }
}
