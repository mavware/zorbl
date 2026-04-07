<?php

namespace App\Console\Commands;

use Database\Seeders\ActivitySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seed:activity')]
#[Description('Download crossword puzzles and seed initial user activity')]
class SeedActivity extends Command
{
    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => ActivitySeeder::class,
            '--no-interaction' => true,
        ]);

        return self::SUCCESS;
    }
}
