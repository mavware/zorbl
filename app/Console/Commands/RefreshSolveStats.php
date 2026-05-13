<?php

namespace App\Console\Commands;

use App\Models\Crossword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crosswords:refresh-stats')]
#[Description('Recalculate cached solve stats for all published puzzles')]
class RefreshSolveStats extends Command
{
    public function handle(): int
    {
        $puzzles = Crossword::where('is_published', true)->get();

        $bar = $this->output->createProgressBar($puzzles->count());
        $bar->start();

        foreach ($puzzles as $puzzle) {
            $puzzle->refreshSolveStats();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Refreshed stats for {$puzzles->count()} puzzles.");

        return self::SUCCESS;
    }
}
