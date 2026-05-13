<?php

namespace Database\Seeders\Activity;

use App\Models\Achievement;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use Illuminate\Support\Facades\DB;

class AchievementsSeeder extends BaseActivitySeeder
{
    protected function runStep(): void
    {
        DB::disableQueryLog();

        $now = now();

        $solversWithCompletions = PuzzleAttempt::where('is_completed', true)
            ->whereHas('user', fn ($q) => $q->where('email', 'like', 'solver%@example.com'))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->take(20)
            ->all();

        $constructorIds = Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->all();

        if ($solversWithCompletions === [] && $constructorIds === []) {
            $this->log('No example users with attempts or crosswords. Run earlier steps first.', 'error');

            return;
        }

        $existingFirstSolve = Achievement::where('type', 'first_solve')
            ->whereIn('user_id', $solversWithCompletions)
            ->pluck('user_id')
            ->flip()
            ->all();

        $existingConstructor = Achievement::where('type', 'constructor')
            ->whereIn('user_id', $constructorIds)
            ->pluck('user_id')
            ->flip()
            ->all();

        $batch = [];

        foreach ($solversWithCompletions as $userId) {
            if (isset($existingFirstSolve[$userId])) {
                continue;
            }

            $batch[] = [
                'user_id' => $userId,
                'type' => 'first_solve',
                'label' => 'First Solve',
                'description' => 'Completed your first crossword puzzle',
                'icon' => 'trophy',
                'earned_at' => $now->copy()->subDays(random_int(1, 30)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($constructorIds as $constructorId) {
            if (isset($existingConstructor[$constructorId])) {
                continue;
            }

            $batch[] = [
                'user_id' => $constructorId,
                'type' => 'constructor',
                'label' => 'Constructor',
                'description' => 'Published your first crossword puzzle',
                'icon' => 'pencil-square',
                'earned_at' => $now->copy()->subDays(random_int(10, 60)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($batch !== []) {
            Achievement::insert($batch);
        }

        $this->log('Created '.count($batch).' achievement(s).');
    }
}
