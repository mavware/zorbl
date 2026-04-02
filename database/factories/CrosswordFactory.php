<?php

namespace Database\Factories;

use App\Models\Crossword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Crossword>
 */
class CrosswordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $width = 15;
        $height = 15;

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'width' => $width,
            'height' => $height,
            'kind' => 'http://ipuz.org/crossword#1',
            'grid' => Crossword::emptyGrid($width, $height),
            'solution' => Crossword::emptySolution($width, $height),
            'clues_across' => [],
            'clues_down' => [],
            'is_published' => false,
        ];
    }

    /**
     * Mark the puzzle as published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    /**
     * Add a symmetric block pattern to the grid.
     */
    public function withBlocks(): static
    {
        return $this->state(function (array $attributes) {
            $width = $attributes['width'];
            $height = $attributes['height'];
            $grid = Crossword::emptyGrid($width, $height);

            $blockPositions = [
                [0, 4], [0, 10],
                [1, 4], [1, 10],
                [2, 4], [2, 10],
                [3, 0], [3, 1], [3, 7],
                [4, 5], [4, 6], [4, 11], [4, 12], [4, 13], [4, 14],
            ];

            foreach ($blockPositions as [$row, $col]) {
                $grid[$row][$col] = '#';
                $symRow = $height - 1 - $row;
                $symCol = $width - 1 - $col;
                $grid[$symRow][$symCol] = '#';
            }

            return ['grid' => $grid];
        });
    }

    /**
     * Fill the solution grid with random letters.
     */
    public function withSolution(): static
    {
        return $this->state(function (array $attributes) {
            $width = $attributes['width'];
            $height = $attributes['height'];
            $grid = $attributes['grid'];
            $solution = Crossword::emptySolution($width, $height);

            for ($row = 0; $row < $height; $row++) {
                for ($col = 0; $col < $width; $col++) {
                    if ($grid[$row][$col] === '#') {
                        $solution[$row][$col] = '#';
                    } else {
                        $solution[$row][$col] = chr(random_int(65, 90));
                    }
                }
            }

            return ['solution' => $solution];
        });
    }
}
