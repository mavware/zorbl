<?php

namespace App\Services;

use App\Models\Word;
use Illuminate\Support\Facades\Cache;

class WordSuggester
{
    /**
     * How long the suggestions for a fully-empty slot stay cached. The word
     * list only changes via bulk imports, so stale results for this long
     * are harmless.
     */
    private const int EMPTY_SLOT_CACHE_SECONDS = 3600;

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

        // A fully-empty slot yields the same top words for every puzzle, and the
        // editor asks for it every time a user clicks into a blank entry — serve
        // it from the cache rather than scanning the word table each time.
        if (trim($pattern, '_') === '') {
            return Cache::remember(
                self::emptySlotCacheKey($length, $limit, $minScore),
                self::EMPTY_SLOT_CACHE_SECONDS,
                fn (): array => $this->query($pattern, $length, $limit, $minScore),
            );
        }

        return $this->query($pattern, $length, $limit, $minScore);
    }

    private static function emptySlotCacheKey(int $length, int $limit, float $minScore): string
    {
        return "word-suggest:empty:{$length}:{$limit}:{$minScore}";
    }

    /**
     * @return array<int, array{word: string, score: float}>
     */
    private function query(string $pattern, int $length, int $limit, float $minScore): array
    {
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
