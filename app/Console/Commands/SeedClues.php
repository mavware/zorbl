<?php

namespace App\Console\Commands;

use Database\Seeders\ClueEntrySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seed:clues')]
#[Description('Download and seed the crossword clue library')]
class SeedClues extends Command
{
    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => ClueEntrySeeder::class,
            '--no-interaction' => true,
        ]);

        return self::SUCCESS;
    }
}
