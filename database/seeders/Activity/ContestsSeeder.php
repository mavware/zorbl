<?php

namespace Database\Seeders\Activity;

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ContestsSeeder extends BaseActivitySeeder
{
    protected function runStep(): void
    {
        DB::disableQueryLog();

        $now = now();

        $constructorIds = Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->all();

        if ($constructorIds === []) {
            $this->log('No example constructors found. Run earlier steps first.', 'error');

            return;
        }

        $contestCreator = $constructorIds[0];

        $crosswordIds = Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->pluck('id')
            ->all();

        if ($crosswordIds === []) {
            $this->log('No example crosswords found. Run the crosswords step first.', 'error');

            return;
        }

        $activeContest = Contest::where('user_id', $contestCreator)->where('status', 'active')->first();

        if ($activeContest === null) {
            $activeContest = Contest::factory()->active()->featured()->create(['user_id' => $contestCreator]);
            $this->attachContestPuzzles($activeContest, array_slice($crosswordIds, 0, 5), $now);
        } else {
            $this->log('Active contest already exists, skipping creation.');
        }

        $upcomingContest = Contest::where('user_id', $contestCreator)->where('status', 'upcoming')->first();

        if ($upcomingContest === null) {
            $upcomingContest = Contest::factory()->upcoming()->create(['user_id' => $contestCreator]);
            $this->attachContestPuzzles($upcomingContest, array_slice($crosswordIds, 5, 3), $now);
        } else {
            $this->log('Upcoming contest already exists, skipping creation.');
        }

        $solverIds = User::where('email', 'like', 'solver%@example.com')->pluck('id')->all();
        $entrants = array_slice($solverIds, 0, 15);

        $existingEntrants = ContestEntry::where('contest_id', $activeContest->id)
            ->whereIn('user_id', $entrants)
            ->pluck('user_id')
            ->flip()
            ->all();

        $entryBatch = [];
        $contestPuzzleCount = $activeContest->crosswords()->count();

        foreach ($entrants as $i => $userId) {
            if (isset($existingEntrants[$userId])) {
                continue;
            }

            $puzzlesCompleted = random_int(0, $contestPuzzleCount);
            $entryBatch[] = [
                'contest_id' => $activeContest->id,
                'user_id' => $userId,
                'registered_at' => $now->copy()->subDays(random_int(0, 1)),
                'total_solve_time_seconds' => $puzzlesCompleted > 0 ? random_int(300, 5400) : null,
                'puzzles_completed' => $puzzlesCompleted,
                'meta_answer' => null,
                'meta_solved' => false,
                'meta_submitted_at' => null,
                'meta_attempts_count' => 0,
                'rank' => $puzzlesCompleted > 0 ? $i + 1 : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($entryBatch !== []) {
            ContestEntry::insert($entryBatch);
        }

        $this->log('Created '.count($entryBatch).' contest entry/entries.');
    }

    /**
     * @param  array<int, int>  $crosswordIds
     */
    private function attachContestPuzzles(Contest $contest, array $crosswordIds, CarbonInterface $now): void
    {
        foreach ($crosswordIds as $i => $crosswordId) {
            $contest->crosswords()->attach($crosswordId, [
                'sort_order' => $i + 1,
                'extraction_hint' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
