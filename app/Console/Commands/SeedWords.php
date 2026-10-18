<?php

namespace App\Console\Commands;

use Database\Seeders\WordListSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seed:words')]
#[Description('Load the words table from the bundled word list, database/data/wordlist.txt')]
class SeedWords extends Command
{
    public function handle(): int
    {
        $command = 'db:seed';
        $args = ['--class' => WordListSeeder::class, '--no-interaction' => true];

        if ($this->call($command, $args) !== self::SUCCESS) {
            $this->error("Failed at: {$command}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
