<?php

namespace App\Console\Commands;

use App\Services\Wordplay\BeheadmentChainsFinder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('find:beheadment-chains {--top=10 : how many of the longest chains to display} {--min-length=1 : shortest word allowed in a chain} {--mode=any : which deletions are allowed: any (STARTLING-style, any position), front (true beheadment), back (curtailment)}')]
#[Description('Find the longest letter-deletion chains: sequences where each step is the previous word minus one letter and is itself a valid word (e.g. STARTLING -> STARTING -> STARING -> STRING -> STING -> SING -> SIN -> IN -> I)')]
class FindBeheadmentChains extends Command
{
    public function handle(BeheadmentChainsFinder $finder): int
    {
        $top = max(1, (int) $this->option('top'));
        $minLength = max(1, (int) $this->option('min-length'));
        $mode = (string) $this->option('mode');

        $results = $finder->find($minLength, $mode, $top);

        foreach ($results as $result) {
            $this->line(sprintf('%2d: %s', count($result['chain']), implode(' -> ', $result['chain'])));
        }

        return self::SUCCESS;
    }
}
