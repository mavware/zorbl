<?php

namespace App\Services;

use App\Models\Word;

class WordSuggester
{
    /**
     * Suggest words matching a pattern.
     *
     * @param  string  $pattern  e.g., "C__NK" where _ = unknown letter
     * @param  int  $length  expected word length
     * @param  int  $limit  max results to return
     * @return array<int, array{word: string, score: float}>
     */
    public function suggest(string $pattern, int $length, int $limit = 20): array
    {
        $pattern = strtoupper($pattern);

        // If fully filled (no unknowns), nothing to suggest
        if (! str_contains($pattern, '_')) {
            return [];
        }

        // SQL LIKE already uses _ as single-char wildcard — pattern maps directly
        return Word::where('length', $length)
            ->where('word', 'LIKE', $pattern)
            ->orderByDesc('score')
            ->limit($limit)
            ->get(['word', 'score'])
            ->map(fn (Word $w) => ['word' => $w->word, 'score' => round($w->score, 1)])
            ->all();
    }
}
