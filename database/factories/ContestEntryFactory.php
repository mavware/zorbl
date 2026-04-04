<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContestEntry>
 */
class ContestEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contest_id' => Contest::factory(),
            'user_id' => User::factory(),
            'registered_at' => now(),
            'total_solve_time_seconds' => null,
            'puzzles_completed' => 0,
            'meta_answer' => null,
            'meta_solved' => false,
            'meta_submitted_at' => null,
            'meta_attempts_count' => 0,
            'rank' => null,
        ];
    }

    /**
     * Entry where the user has solved the meta.
     */
    public function metaSolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'meta_answer' => strtoupper(fake()->word()),
            'meta_solved' => true,
            'meta_submitted_at' => now(),
            'meta_attempts_count' => 1,
        ]);
    }

    /**
     * Entry with a solve time.
     */
    public function withSolveTime(int $seconds = 300): static
    {
        return $this->state(fn (array $attributes) => [
            'total_solve_time_seconds' => $seconds,
        ]);
    }
}
