<?php

namespace App\Console\Commands;

use App\Models\Crossword;
use App\Models\User;
use App\Notifications\SolverWeeklyDigest;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('solvers:send-weekly-digest {--since= : Start of the reporting period (Y-m-d), defaults to 7 days ago}')]
#[Description('Send a weekly solving recap email to users who solved puzzles recently')]
class SendSolverWeeklyDigests extends Command
{
    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))->startOfDay()
            : Carbon::now()->subWeek()->startOfDay();

        $solvers = User::whereHas('puzzleAttempts', fn ($q) => $q->where('created_at', '>=', $since))
            ->get();

        $newPuzzlesCount = Crossword::where('is_published', true)
            ->where('created_at', '>=', $since)
            ->count();

        $sent = 0;
        $skipped = 0;

        foreach ($solvers as $solver) {
            $stats = $this->gatherStats($solver, $since, $newPuzzlesCount);

            $hasActivity = $stats['puzzles_solved'] > 0 || $stats['puzzles_completed'] > 0;

            if (! $hasActivity) {
                $skipped++;

                continue;
            }

            $solver->notify(new SolverWeeklyDigest($stats));
            $sent++;
        }

        $this->info("Sent {$sent} digest(s), skipped {$skipped} solver(s) with no activity.");

        return self::SUCCESS;
    }

    /**
     * @return array{puzzles_solved: int, puzzles_completed: int, total_solve_time_seconds: int, current_streak: int, longest_streak: int, best_puzzle: array{title: string, solve_time_seconds: int}|null, new_puzzles_available: int}
     */
    private function gatherStats(User $solver, CarbonInterface $since, int $newPuzzlesCount): array
    {
        $attempts = $solver->puzzleAttempts()
            ->where('created_at', '>=', $since);

        $puzzlesSolved = $attempts->count();

        $puzzlesCompleted = (clone $attempts)
            ->where('is_completed', true)
            ->where('completed_at', '>=', $since)
            ->count();

        $totalSolveTime = (clone $attempts)
            ->where('is_completed', true)
            ->where('completed_at', '>=', $since)
            ->sum('solve_time_seconds');

        $bestPuzzle = null;

        if ($puzzlesCompleted > 0) {
            $fastest = $solver->puzzleAttempts()
                ->where('created_at', '>=', $since)
                ->where('is_completed', true)
                ->where('completed_at', '>=', $since)
                ->where('solve_time_seconds', '>', 0)
                ->orderBy('solve_time_seconds')
                ->first();

            if ($fastest) {
                $crossword = Crossword::find($fastest->crossword_id);
                $bestPuzzle = [
                    'title' => $crossword?->displayTitle() ?? __('Untitled'),
                    'solve_time_seconds' => $fastest->solve_time_seconds,
                ];
            }
        }

        return [
            'puzzles_solved' => $puzzlesSolved,
            'puzzles_completed' => $puzzlesCompleted,
            'total_solve_time_seconds' => (int) $totalSolveTime,
            'current_streak' => $solver->current_streak,
            'longest_streak' => $solver->longest_streak,
            'best_puzzle' => $bestPuzzle,
            'new_puzzles_available' => $newPuzzlesCount,
        ];
    }
}
