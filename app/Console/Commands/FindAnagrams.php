<?php

namespace App\Console\Commands;

use App\Models\Word;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:anagrams {--min-length=3 : ignore words shorter than this} {--min-group-size=2 : only show anagram groups with at least this many members} {--top= : show only the N largest groups (omit for all)}')]
#[Description('Group words by their sorted-letter signature and report each set of anagrams (e.g. STARE, RATES, TEARS, ASTER, RESAT, TARES)')]
class FindAnagrams extends Command
{
    public function handle(): int
    {
        $minLength = max(1, (int) $this->option('min-length'));
        $minGroupSize = max(2, (int) $this->option('min-group-size'));
        $top = (int) $this->option('top');

        $words = Word::query()
            ->where('length', '>=', $minLength)
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();

        $this->info(sprintf('Grouping %d words by anagram signature...', count($words)));

        $groups = [];
        foreach ($words as $word) {
            $chars = str_split($word);
            sort($chars);
            $key = implode('', $chars);
            $groups[$key][] = $word;
        }

        $groups = array_filter($groups, fn (array $group): bool => count($group) >= $minGroupSize);
        uasort($groups, fn (array $a, array $b): int => count($b) <=> count($a));

        if ($top > 0) {
            $groups = array_slice($groups, 0, $top, true);
        }

        foreach ($groups as $group) {
            $this->line(sprintf('%2d: %s', count($group), implode(', ', $group)));
        }

        $this->info(sprintf('Done. %d anagram groups.', count($groups)));

        return self::SUCCESS;
    }
}
