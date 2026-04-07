<?php

namespace App\Console\Commands;

use App\Models\ClueEntry;
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
        $before = ClueEntry::count();

        try {
            $this->call('db:seed', [
                '--class' => ClueEntrySeeder::class,
                '--no-interaction' => true,
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $after = ClueEntry::count();

        if ($after === 0) {
            $this->error('No clue entries were seeded.');

            return self::FAILURE;
        }

        $this->info("Clue entries: {$before} → {$after}");

        return self::SUCCESS;
    }
}
