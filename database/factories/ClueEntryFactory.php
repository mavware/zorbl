<?php

namespace Database\Factories;

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClueEntry>
 */
class ClueEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'answer' => strtoupper(fake()->lexify('?????')),
            'clue' => fake()->sentence(),
            'crossword_id' => Crossword::factory(),
            'user_id' => User::factory(),
            'direction' => fake()->randomElement(['across', 'down']),
            'clue_number' => fake()->numberBetween(1, 50),
        ];
    }

    public function standalone(): static
    {
        return $this->state(fn (array $attributes) => [
            'crossword_id' => null,
            'direction' => null,
            'clue_number' => null,
        ]);
    }
}
