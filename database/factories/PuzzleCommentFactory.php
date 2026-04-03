<?php

namespace Database\Factories;

use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PuzzleComment>
 */
class PuzzleCommentFactory extends Factory
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
            'body' => fake()->sentence(),
            'rating' => fake()->numberBetween(1, 5),
        ];
    }
}
