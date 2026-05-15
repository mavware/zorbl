<?php

namespace Database\Seeders\Activity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends BaseActivitySeeder
{
    protected function runStep(): void
    {
        $puzzles = $this->loadPuzzles();

        if (count($puzzles) < 5) {
            $this->log('Not enough valid puzzles found. Need at least 5. Got '.count($puzzles).'.', 'error');

            return;
        }

        DB::disableQueryLog();

        $now = now();
        $hashedPassword = Hash::make('password');

        $seedPuzzles = array_slice($puzzles, 0, self::PUZZLE_COUNT);
        $authorNameToEmail = $this->authorEmailMap($seedPuzzles);

        $constructorBatch = [];

        foreach ($authorNameToEmail as $name => $email) {
            $constructorBatch[] = [
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'email_verified_at' => $now,
                'current_streak' => 0,
                'longest_streak' => 0,
                'last_solve_date' => null,
                'created_at' => $now->copy()->subDays(random_int(30, 90)),
                'updated_at' => $now,
            ];
        }

        $constructorBatch = $this->filterOutExistingEmails($constructorBatch);

        foreach (array_chunk($constructorBatch, 500) as $chunk) {
            User::insert($chunk);
        }

        $solverBatch = [];

        for ($i = 1; $i <= self::SOLVER_COUNT; $i++) {
            $email = "solver{$i}@example.com";
            $solverBatch[] = [
                'name' => fake()->name(),
                'email' => $email,
                'password' => $hashedPassword,
                'email_verified_at' => $now,
                'current_streak' => random_int(0, 15),
                'longest_streak' => random_int(1, 30),
                'last_solve_date' => $now->copy()->subDays(random_int(0, 7))->toDateString(),
                'created_at' => $now->copy()->subDays(random_int(1, 60)),
                'updated_at' => $now,
            ];
        }

        $solverBatch = $this->filterOutExistingEmails($solverBatch);

        foreach (array_chunk($solverBatch, 500) as $chunk) {
            User::insert($chunk);
        }

        $this->log('Created '.count($constructorBatch).' constructor(s) and '.count($solverBatch).' solver(s).');
    }
}
