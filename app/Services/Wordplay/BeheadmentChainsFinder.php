<?php

namespace App\Services\Wordplay;

use App\Models\Word;
use InvalidArgumentException;

class BeheadmentChainsFinder
{
    public const MODE_ANY = 'any';

    public const MODE_FRONT = 'front';

    public const MODE_BACK = 'back';

    /**
     * @return list<array{word: string, chain: list<string>}>
     */
    public function find(int $minLength = 1, string $mode = self::MODE_ANY, int $top = 10): array
    {
        if (! in_array($mode, [self::MODE_ANY, self::MODE_FRONT, self::MODE_BACK], true)) {
            throw new InvalidArgumentException("Invalid mode '{$mode}'.");
        }

        $words = Word::query()
            ->where('length', '>=', $minLength)
            ->orderBy('length')
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();
        $wordSet = array_flip($words);

        $chainLength = [];
        $next = [];

        foreach ($words as $word) {
            $best = 1;
            $bestChild = null;

            foreach ($this->deletions($word, $mode) as $child) {
                if (! isset($wordSet[$child])) {
                    continue;
                }
                $candidate = 1 + ($chainLength[$child] ?? 1);
                if ($candidate > $best) {
                    $best = $candidate;
                    $bestChild = $child;
                }
            }

            $chainLength[$word] = $best;
            if ($bestChild !== null) {
                $next[$word] = $bestChild;
            }
        }

        arsort($chainLength);

        $results = [];
        foreach ($chainLength as $word => $length) {
            if ($length < 2) {
                break;
            }
            $results[] = ['word' => $word, 'chain' => $this->reconstruct($word, $next)];
            if (count($results) >= $top) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return iterable<string>
     */
    private function deletions(string $word, string $mode): iterable
    {
        $length = strlen($word);
        if ($length <= 1) {
            return;
        }

        if ($mode === self::MODE_FRONT) {
            yield substr($word, 1);

            return;
        }

        if ($mode === self::MODE_BACK) {
            yield substr($word, 0, -1);

            return;
        }

        for ($i = 0; $i < $length; $i++) {
            yield substr($word, 0, $i).substr($word, $i + 1);
        }
    }

    /**
     * @param  array<string, string>  $next
     * @return list<string>
     */
    private function reconstruct(string $word, array $next): array
    {
        $chain = [$word];
        while (isset($next[$word])) {
            $word = $next[$word];
            $chain[] = $word;
        }

        return $chain;
    }
}
