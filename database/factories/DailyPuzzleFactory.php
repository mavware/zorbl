<?php

namespace Database\Factories;

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyPuzzle>
 */
class DailyPuzzleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'crossword_id' => Crossword::factory(),
            'selected_by' => null,
        ];
    }
}
