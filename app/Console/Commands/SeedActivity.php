<?php

namespace App\Console\Commands;

use Database\Seeders\Activity\AchievementsSeeder;
use Database\Seeders\Activity\AttemptsSeeder;
use Database\Seeders\Activity\ContestsSeeder;
use Database\Seeders\Activity\CrosswordsSeeder;
use Database\Seeders\Activity\SocialSeeder;
use Database\Seeders\Activity\UsersSeeder;
use Database\Seeders\ActivitySeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seed:activity {--step= : Run a single step: users, crosswords, attempts, social, achievements, or contests}')]
#[Description('Seed initial user activity. Runs all steps in order, or a single step via --step=<name>.')]
class SeedActivity extends Command
{
    private const array STEPS = [
        'users' => UsersSeeder::class,
        'crosswords' => CrosswordsSeeder::class,
        'attempts' => AttemptsSeeder::class,
        'social' => SocialSeeder::class,
        'achievements' => AchievementsSeeder::class,
        'contests' => ContestsSeeder::class,
    ];

    public function handle(): int
    {
        $step = $this->option('step');

        if ($step !== null) {
            if (! isset(self::STEPS[$step])) {
                $this->error("Unknown step '{$step}'. Valid steps: ".implode(', ', array_keys(self::STEPS)));

                return self::FAILURE;
            }

            return $this->call('db:seed', [
                '--class' => self::STEPS[$step],
                '--no-interaction' => true,
            ]);
        }

        return $this->call('db:seed', [
            '--class' => ActivitySeeder::class,
            '--no-interaction' => true,
        ]);
    }
}
