<?php

namespace App\Console\Commands;

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

        $steps = [
            ['Step 1/4: Seeding clue library...', 'seed:clues', []],
            ['Step 2/4: Generating word list...', 'crossword:generate-wordlist', []],
            ['Step 3/4: Seeding words table...', 'db:seed', ['--class' => WordListSeeder::class, '--no-interaction' => true]],
            ['Step 4/4: Seeding activity data...', 'seed:activity', []],
        ];

        foreach ($steps as [$message, $command, $args]) {
            $this->info($message);

            if ($this->call($command, $args) !== self::SUCCESS) {
                $this->error("Failed at: {$command}");

                return self::FAILURE;
            }
        }

        $this->info('Platform setup complete.');

        return self::SUCCESS;
    }
}
