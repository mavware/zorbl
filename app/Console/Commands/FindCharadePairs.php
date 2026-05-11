<?php

namespace App\Console\Commands;

use App\Services\Wordplay\CharadePairsFinder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:charade-pairs {--size= : sample size of words (omit for all)} {--min-length=3 : ignore words shorter than this on either side of a split}')]
#[Description('Find charade pairs: compound strings that decompose into valid words at more than one split position (e.g. SUPER+MANHOOD vs SUPERMAN+HOOD)')]
class FindCharadePairs extends Command
{
    public function handle(CharadePairsFinder $finder): int
    {
        $size = (int) $this->option('size');
        $minLength = max(1, (int) $this->option('min-length'));

        $results = $finder->find($size, $minLength);

        foreach ($results as $result) {
            $description = collect($result['splits'])
                ->map(fn (array $split): string => "{$split[0]}+{$split[1]}")
                ->implode(' | ');
            $this->line("{$result['word']}: {$description}");
        }

        $this->info(sprintf('Done. Found %d charade pairs.', count($results)));

        return self::SUCCESS;
    }
}
