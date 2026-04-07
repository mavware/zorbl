<?php

namespace Database\Factories;

use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Word>
 */
class WordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = mb_strtoupper($this->faker->unique()->lexify('?????'));

        return [
            'word' => $word,
            'length' => mb_strlen($word),
            'score' => round($this->faker->randomFloat(2, 10, 90), 2),
        ];
    }

    /**
     * Create a word with a specific word string.
     */
    public function word(string $word): static
    {
        $upper = mb_strtoupper($word);

        return $this->state([
            'word' => $upper,
            'length' => mb_strlen($upper),
        ]);
    }
}
