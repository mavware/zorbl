<?php

namespace Database\Seeders\Activity;

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttemptsSeeder extends BaseActivitySeeder
{
    protected function runStep(): void
    {
        DB::disableQueryLog();

        $solverIds = User::where('email', 'like', 'solver%@example.com')->pluck('id')->all();

        if ($solverIds === []) {
            $this->log('No solver users found. Run the users step first.', 'error');

            return;
        }

        $crosswordIds = Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->pluck('id')
            ->all();

        if ($crosswordIds === []) {
            $this->log('No crosswords found. Run the crosswords step first.', 'error');

            return;
        }

        $now = now();
        $existingAttempts = PuzzleAttempt::whereIn('user_id', $solverIds)
            ->whereIn('crossword_id', $crosswordIds)
            ->select('user_id', 'crossword_id')
            ->get()
            ->mapWithKeys(fn ($a) => ["{$a->user_id}-{$a->crossword_id}" => true])
            ->all();

        $attemptBatch = [];

        foreach ($solverIds as $userId) {
            $numAttempts = random_int(1, 5);
            $puzzlePool = $crosswordIds;
            shuffle($puzzlePool);

            for ($j = 0; $j < $numAttempts && $j < count($puzzlePool); $j++) {
                $crosswordId = $puzzlePool[$j];
                $key = "{$userId}-{$crosswordId}";

                if (isset($existingAttempts[$key])) {
                    continue;
                }

                $existingAttempts[$key] = true;
                $isCompleted = random_int(1, 100) <= 70;
                $solveTime = random_int(120, 3600);
                $startedAt = $now->copy()->subHours(random_int(1, 720));

                $attemptBatch[] = [
                    'user_id' => $userId,
                    'crossword_id' => $crosswordId,
                    'progress' => json_encode([]),
                    'is_completed' => $isCompleted,
                    'started_at' => $startedAt,
                    'completed_at' => $isCompleted ? $startedAt->copy()->addSeconds($solveTime) : null,
                    'solve_time_seconds' => $isCompleted ? $solveTime : null,
                    'created_at' => $startedAt,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($attemptBatch, 500) as $chunk) {
            PuzzleAttempt::insert($chunk);
        }

        $this->log('Created '.count($attemptBatch).' puzzle attempt(s).');
    }
}
