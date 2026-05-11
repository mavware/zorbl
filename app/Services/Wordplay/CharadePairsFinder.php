<?php

namespace App\Services\Wordplay;

use App\Models\Word;

class CharadePairsFinder
{
    /**
     * @return list<array{word: string, splits: list<array{0: string, 1: string}>}>
     */
    public function find(int $sampleSize = 0, int $minLength = 3, int $limit = 0): array
    {
        $allWords = $this->loadDictionary($minLength);
        $wordSet = array_flip($allWords);
        $sample = $this->sampleWords($allWords, $sampleSize);

        $checked = [];
        $results = [];

        foreach ($sample as $a) {
            foreach ($sample as $b) {
                $compound = $a.$b;
                if (isset($checked[$compound])) {
                    continue;
                }
                $checked[$compound] = true;

                $splits = $this->findSplits($compound, $wordSet, $minLength);
                if (count($splits) < 2) {
                    continue;
                }

                $results[] = ['word' => $compound, 'splits' => $splits];

                if ($limit > 0 && count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function loadDictionary(int $minLength): array
    {
        return Word::query()
            ->where('length', '>=', $minLength)
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();
    }

    /**
     * @param  list<string>  $allWords
     * @return list<string>
     */
    private function sampleWords(array $allWords, int $size): array
    {
        if ($size <= 0 || $size >= count($allWords)) {
            return $allWords;
        }

        $keys = array_rand($allWords, $size);

        return array_values(array_intersect_key($allWords, array_flip((array) $keys)));
    }

    /**
     * @param  array<string, int>  $wordSet
     * @return list<array{0: string, 1: string}>
     */
    private function findSplits(string $compound, array $wordSet, int $minLength): array
    {
        $splits = [];
        $length = strlen($compound);
        $end = $length - $minLength;

        for ($i = $minLength; $i <= $end; $i++) {
            $left = substr($compound, 0, $i);
            $right = substr($compound, $i);

            if (isset($wordSet[$left]) && isset($wordSet[$right])) {
                $splits[] = [$left, $right];
            }
        }

        return $splits;
    }
}
