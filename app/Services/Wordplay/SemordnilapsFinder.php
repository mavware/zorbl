<?php

namespace App\Services\Wordplay;

use App\Models\Word;

class SemordnilapsFinder
{
    /**
     * @return list<array{word: string, reverse: string}>
     */
    public function find(int $minLength = 3, int $limit = 0): array
    {
        $words = Word::query()
            ->where('length', '>=', $minLength)
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();
        $wordSet = array_flip($words);

        $results = [];
        foreach ($words as $word) {
            $reversed = strrev($word);

            if ($word >= $reversed) {
                continue;
            }

            if (! isset($wordSet[$reversed])) {
                continue;
            }

            $results[] = ['word' => $word, 'reverse' => $reversed];

            if ($limit > 0 && count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
