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
     * @param  float  $minScore  filter out words below this score (0 = no filter)
     * @return array<int, array{word: string, score: float}>
     */
    public function suggest(string $pattern, int $length, int $limit = 20, float $minScore = 0): array
    {
        $pattern = strtoupper($pattern);

        // If fully filled (no unknowns), nothing to suggest
        if (! str_contains($pattern, '_')) {
            return [];
        }

        // SQL LIKE already uses _ as single-char wildcard — pattern maps directly
        $query = Word::where('length', $length)
            ->where('word', 'LIKE', $pattern);

        if ($minScore > 0) {
            $query->where('score', '>=', $minScore);
        }

        return $query->orderByDesc('score')
            ->limit($limit)
            ->get(['word', 'score'])
            ->map(fn (Word $w) => ['word' => $w->word, 'score' => round($w->score, 1)])
            ->all();
    }
}
