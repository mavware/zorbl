<?php

namespace App\Console\Commands;

use App\Models\Word;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:palindromes {--min-length=3 : ignore words shorter than this}')]
#[Description('Find palindromes: words that read the same forwards and backwards (e.g. RACECAR, LEVEL, ROTOR)')]
class FindPalindromes extends Command
{
    public function handle(): int
    {
        $minLength = max(2, (int) $this->option('min-length'));

        $words = Word::query()
            ->where('length', '>=', $minLength)
            ->orderBy('length')
            ->orderBy('word')
            ->pluck('word')
            ->map(fn (string $word): string => strtoupper($word))
            ->all();

        $this->info(sprintf('Scanning %d words for palindromes...', count($words)));

        $found = 0;
        foreach ($words as $word) {
            if ($word === strrev($word)) {
                $found++;
                $this->line($word);
            }
        }

        $this->info("Done. Found {$found} palindromes.");

        return self::SUCCESS;
    }
}
