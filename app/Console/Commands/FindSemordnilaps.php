<?php

namespace App\Console\Commands;

use App\Services\Wordplay\SemordnilapsFinder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:semordnilaps {--min-length=3 : ignore words shorter than this}')]
#[Description('Find semordnilaps: words that spell a different valid word when reversed (e.g. STRESSED <-> DESSERTS)')]
class FindSemordnilaps extends Command
{
    public function handle(SemordnilapsFinder $finder): int
    {
        $minLength = max(2, (int) $this->option('min-length'));

        $results = $finder->find($minLength);

        foreach ($results as $result) {
            $this->line("{$result['word']} <-> {$result['reverse']}");
        }

        $this->info(sprintf('Done. Found %d semordnilap pairs.', count($results)));

        return self::SUCCESS;
    }
}
