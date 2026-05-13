<?php

namespace Database\Seeders;

use Database\Seeders\Activity\AchievementsSeeder;
use Database\Seeders\Activity\AttemptsSeeder;
use Database\Seeders\Activity\ContestsSeeder;
use Database\Seeders\Activity\CrosswordsSeeder;
use Database\Seeders\Activity\SocialSeeder;
use Database\Seeders\Activity\UsersSeeder;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            CrosswordsSeeder::class,
            AttemptsSeeder::class,
            SocialSeeder::class,
            AchievementsSeeder::class,
            ContestsSeeder::class,
        ]);
    }
}
