<?php

namespace App\Console\Commands;

use App\Models\Word;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:word-ladder {start : starting word} {end : target word} {--max-steps=20 : abort if no ladder within this many steps exists}')]
#[Description('Find the shortest word ladder from start to end. Each rung changes one letter and is itself a valid word (e.g. COLD -> CORD -> WORD -> WARD -> WARM).')]
class FindWordLadder extends Command
{
    public function handle(): int
    {
        $start = strtoupper((string) $this->argument('start'));
        $end = strtoupper((string) $this->argument('end'));
        $maxSteps = max(1, (int) $this->option('max-steps'));

        if (strlen($start) !== strlen($end)) {
            $this->error('Start and end words must be the same length.');

            return self::FAILURE;
        }

        if ($start === $end) {
            $this->line($start);

            return self::SUCCESS;
        }

        $length = strlen($start);

        $words = Word::query()
            ->where('length', $length)
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();
        $wordSet = array_flip($words);

        foreach ([$start, $end] as $candidate) {
            if (! isset($wordSet[$candidate])) {
                $this->warn("'{$candidate}' is not in the dictionary; a ladder may still exist if reachable from a neighbor.");
            }
        }

        $buckets = $this->buildWildcardBuckets($words, $length);

        $parents = [$start => null];
        $current = [$start];
        $steps = 0;

        while ($current !== [] && $steps < $maxSteps) {
            $next = [];
            foreach ($current as $word) {
                for ($i = 0; $i < $length; $i++) {
                    $pattern = substr_replace($word, '*', $i, 1);
                    if (! isset($buckets[$pattern])) {
                        continue;
                    }
                    foreach ($buckets[$pattern] as $neighbor) {
                        if (array_key_exists($neighbor, $parents)) {
                            continue;
                        }
                        $parents[$neighbor] = $word;
                        $next[] = $neighbor;
                    }
                }
            }
            $steps++;
            if (array_key_exists($end, $parents)) {
                break;
            }
            $current = $next;
        }

        if (! array_key_exists($end, $parents)) {
            $this->info("No ladder from {$start} to {$end} found within {$maxSteps} steps.");

            return self::SUCCESS;
        }

        $path = $this->reconstruct($end, $parents);

        $this->info(sprintf('Ladder (%d steps):', count($path) - 1));
        foreach ($path as $rung) {
            $this->line($rung);
        }

        return self::SUCCESS;
    }

    /**
     * Bucket every dictionary word by each of its single-letter-wildcard patterns.
     * Two words share a bucket iff they differ at exactly that one position.
     *
     * @param  list<string>  $words
     * @return array<string, list<string>>
     */
    private function buildWildcardBuckets(array $words, int $length): array
    {
        $buckets = [];
        foreach ($words as $word) {
            for ($i = 0; $i < $length; $i++) {
                $pattern = substr_replace($word, '*', $i, 1);
                $buckets[$pattern][] = $word;
            }
        }

        return $buckets;
    }

    /**
     * @param  array<string, string|null>  $parents
     * @return list<string>
     */
    private function reconstruct(string $end, array $parents): array
    {
        $path = [];
        $current = $end;
        while ($current !== null) {
            $path[] = $current;
            $current = $parents[$current];
        }

        return array_reverse($path);
    }
}
