<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('setup:platform')]
#[Description('Seed activity data and generate the crossword word list')]
class SetupPlatform extends Command
{
    public function handle(): int
    {
        $this->info('Setting up platform data...');

        $this->call('seed:activity');
        $this->call('crossword:generate-wordlist');

        $this->info('Platform setup complete.');

        return self::SUCCESS;
    }
}
