<?php

namespace App\Console\Commands;

use App\Models\Word;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:words-inside-words {--min-container-length=8 : ignore container words shorter than this} {--min-substring-length=4 : only count hidden words at least this long} {--min-hidden=2 : only show containers with at least this many hidden words} {--top= : show only the top N containers by hidden-word count}')]
#[Description('Find dictionary words that contain other dictionary words as substrings (e.g. MANSLAUGHTER contains MAN, LAUGH, LAUGHTER)')]
class FindWordsInsideWords extends Command
{
    public function handle(): int
    {
        $minContainer = max(2, (int) $this->option('min-container-length'));
        $minSubstring = max(1, (int) $this->option('min-substring-length'));
        $minHidden = max(1, (int) $this->option('min-hidden'));
        $top = (int) $this->option('top');

        $allWords = Word::query()
            ->where('length', '>=', $minSubstring)
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();
        $wordSet = array_flip($allWords);

        $containers = array_values(array_filter(
            $allWords,
            fn (string $word): bool => strlen($word) >= $minContainer,
        ));

        $this->info(sprintf(
            'Scanning %d containers against a %d-word dictionary...',
            count($containers),
            count($allWords),
        ));

        $results = [];
        foreach ($containers as $container) {
            $hidden = $this->findHidden($container, $wordSet, $minSubstring);
            if (count($hidden) >= $minHidden) {
                $results[$container] = $hidden;
            }
        }

        uasort($results, fn (array $a, array $b): int => count($b) <=> count($a));

        if ($top > 0) {
            $results = array_slice($results, 0, $top, true);
        }

        foreach ($results as $container => $hidden) {
            $this->line(sprintf('%s (%d): %s', $container, count($hidden), implode(', ', $hidden)));
        }

        $this->info(sprintf('Done. %d containers reported.', count($results)));

        return self::SUCCESS;
    }

    /**
     * Return every distinct dictionary word (other than the container itself)
     * that appears as a substring of the container.
     *
     * @param  array<string, int>  $wordSet
     * @return list<string>
     */
    private function findHidden(string $container, array $wordSet, int $minSubstring): array
    {
        $hidden = [];
        $seen = [];
        $length = strlen($container);

        for ($i = 0; $i < $length; $i++) {
            $maxLen = $length - $i;
            for ($len = $minSubstring; $len <= $maxLen; $len++) {
                $candidate = substr($container, $i, $len);
                if ($candidate === $container) {
                    continue;
                }
                if (isset($seen[$candidate])) {
                    continue;
                }
                if (isset($wordSet[$candidate])) {
                    $seen[$candidate] = true;
                    $hidden[] = $candidate;
                }
            }
        }

        return $hidden;
    }
}
