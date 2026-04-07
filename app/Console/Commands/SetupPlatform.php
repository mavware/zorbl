<?php

namespace App\Console\Commands;

use Database\Seeders\ClueEntrySeeder;
use Database\Seeders\WordListSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('setup:platform')]
#[Description('Seed clue library, generate word list, and populate activity data')]
class SetupPlatform extends Command
{
    public function handle(): int
    {
        $this->info('Setting up platform data...');

        $this->info('Step 1/4: Seeding clue library...');
        $this->call('db:seed', [
            '--class' => ClueEntrySeeder::class,
            '--no-interaction' => true,
        ]);

        $this->info('Step 2/4: Generating word list...');
        $this->call('crossword:generate-wordlist');

        $this->info('Step 3/4: Seeding words table...');
        $this->call('db:seed', [
            '--class' => WordListSeeder::class,
            '--no-interaction' => true,
        ]);

        $this->info('Step 4/4: Seeding activity data...');
        $this->call('seed:activity');

        $this->info('Platform setup complete.');

        return self::SUCCESS;
    }
}
