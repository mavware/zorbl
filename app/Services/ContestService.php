<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

class ContestService
{
    /**
     * Register a user for a contest. Returns existing entry if already registered.
     */
    public function register(User $user, Contest $contest): ContestEntry
    {
        return ContestEntry::firstOrCreate(
            ['contest_id' => $contest->id, 'user_id' => $user->id],
            ['registered_at' => now()],
        );
    }

    /**
     * Submit a meta answer for a contest entry.
     * Returns true if the answer is correct.
     */
    public function submitMetaAnswer(ContestEntry $entry, string $answer): bool
    {
        $contest = $entry->contest;

        $entry->increment('meta_attempts_count');
        $entry->meta_answer = $answer;
        $entry->meta_submitted_at = now();

        $isCorrect = $contest->checkMetaAnswer($answer);

        if ($isCorrect) {
            $entry->meta_solved = true;
        }

        $entry->save();

        $this->recalculateLeaderboard($contest);

        return $isCorrect;
    }

    /**
     * Recalculate puzzles_completed and total_solve_time_seconds for a contest entry
     * based on actual PuzzleAttempt records.
     */
    public function syncPuzzleCompletion(ContestEntry $entry): void
    {
        $contest = $entry->contest;
        $crosswordIds = $contest->crosswords()->pluck('crosswords.id');

        $completedAttempts = PuzzleAttempt::where('user_id', $entry->user_id)
            ->whereIn('crossword_id', $crosswordIds)
            ->where('is_completed', true)
            ->get();

        $entry->puzzles_completed = $completedAttempts->count();
        $entry->total_solve_time_seconds = $completedAttempts->sum('solve_time_seconds');
        $entry->save();
    }

    /**
     * Recalculate the leaderboard rankings for all entries in a contest.
     *
     * Ranking order:
     * 1. meta_solved DESC (solvers first)
     * 2. meta_submitted_at ASC (earlier submission wins)
     * 3. total_solve_time_seconds ASC (faster total time wins)
     */
    public function recalculateLeaderboard(Contest $contest): void
    {
        $entries = $contest->entries()
            ->orderByDesc('meta_solved')
            ->orderBy('meta_submitted_at')
            ->orderBy('total_solve_time_seconds')
            ->get();

        $rank = 1;
        foreach ($entries as $entry) {
            $entry->update(['rank' => $rank++]);
        }
    }

    /**
     * Called when a user completes a puzzle that belongs to a contest.
     * Syncs their completion stats and recalculates the leaderboard.
     */
    public function processContestSolve(User $user, Crossword $crossword): void
    {
        $contestIds = $crossword->contests()->pluck('contests.id');

        $entries = ContestEntry::where('user_id', $user->id)
            ->whereIn('contest_id', $contestIds)
            ->get();

        foreach ($entries as $entry) {
            $this->syncPuzzleCompletion($entry);
            $this->recalculateLeaderboard($entry->contest);
        }
    }
}
