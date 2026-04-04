<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\ContestEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class AchievementService
{
    /**
     * Achievement definitions: type => [label, description, icon].
     *
     * @var array<string, array{label: string, description: string, icon: string}>
     */
    public const DEFINITIONS = [
        'first_solve' => [
            'label' => 'First Solve',
            'description' => 'Completed your first crossword puzzle',
            'icon' => 'star',
        ],
        'puzzles_10' => [
            'label' => 'Getting Started',
            'description' => 'Completed 10 crossword puzzles',
            'icon' => 'fire',
        ],
        'puzzles_50' => [
            'label' => 'Dedicated Solver',
            'description' => 'Completed 50 crossword puzzles',
            'icon' => 'trophy',
        ],
        'puzzles_100' => [
            'label' => 'Century Club',
            'description' => 'Completed 100 crossword puzzles',
            'icon' => 'sparkles',
        ],
        'streak_7' => [
            'label' => 'Week Warrior',
            'description' => 'Solved a puzzle every day for 7 days',
            'icon' => 'bolt',
        ],
        'streak_30' => [
            'label' => 'Monthly Master',
            'description' => 'Solved a puzzle every day for 30 days',
            'icon' => 'shield-check',
        ],
        'speed_demon' => [
            'label' => 'Speed Demon',
            'description' => 'Solved a puzzle in under 2 minutes',
            'icon' => 'clock',
        ],
        'first_contest' => [
            'label' => 'Contest Debut',
            'description' => 'Completed all puzzles in a contest',
            'icon' => 'flag',
        ],
        'first_meta_solve' => [
            'label' => 'Meta Mind',
            'description' => 'Solved your first meta puzzle answer',
            'icon' => 'puzzle-piece',
        ],
        'contest_winner' => [
            'label' => 'Champion',
            'description' => 'Finished 1st place in a contest',
            'icon' => 'trophy',
        ],
    ];

    /**
     * Process a puzzle completion: update streak and check for new achievements.
     *
     * @return array<int, Achievement> Newly earned achievements
     */
    public function processSolve(User $user, ?int $solveTimeSeconds = null): array
    {
        $this->updateStreak($user);

        return $this->checkAchievements($user, $solveTimeSeconds);
    }

    /**
     * Update the user's daily solving streak.
     */
    public function updateStreak(User $user): void
    {
        $today = Carbon::today();
        $lastSolve = $user->last_solve_date ? Carbon::parse($user->last_solve_date) : null;

        if ($lastSolve && $lastSolve->isSameDay($today)) {
            // Already solved today — no change
            return;
        }

        if ($lastSolve && $lastSolve->isSameDay($today->copy()->subDay())) {
            // Consecutive day — extend streak
            $user->current_streak++;
        } else {
            // Streak broken or first solve
            $user->current_streak = 1;
        }

        if ($user->current_streak > $user->longest_streak) {
            $user->longest_streak = $user->current_streak;
        }

        $user->last_solve_date = $today;
        $user->save();
    }

    /**
     * Check and award any new achievements.
     *
     * @return array<int, Achievement>
     */
    public function checkAchievements(User $user, ?int $solveTimeSeconds = null): array
    {
        $earned = [];
        $completedCount = $user->puzzleAttempts()->where('is_completed', true)->count();

        // Milestone achievements
        $milestones = [
            'first_solve' => 1,
            'puzzles_10' => 10,
            'puzzles_50' => 50,
            'puzzles_100' => 100,
        ];

        foreach ($milestones as $type => $threshold) {
            if ($completedCount >= $threshold) {
                $achievement = $this->award($user, $type);
                if ($achievement) {
                    $earned[] = $achievement;
                }
            }
        }

        // Streak achievements
        $streakMilestones = [
            'streak_7' => 7,
            'streak_30' => 30,
        ];

        foreach ($streakMilestones as $type => $threshold) {
            if ($user->current_streak >= $threshold) {
                $achievement = $this->award($user, $type);
                if ($achievement) {
                    $earned[] = $achievement;
                }
            }
        }

        // Speed achievement
        if ($solveTimeSeconds !== null && $solveTimeSeconds > 0 && $solveTimeSeconds <= 120) {
            $achievement = $this->award($user, 'speed_demon');
            if ($achievement) {
                $earned[] = $achievement;
            }
        }

        return $earned;
    }

    /**
     * Check and award contest-related achievements.
     *
     * @return array<int, Achievement>
     */
    public function checkContestAchievements(User $user, ContestEntry $entry): array
    {
        $earned = [];

        // Contest Debut: completed all puzzles in a contest
        $totalPuzzles = $entry->contest->crosswords()->count();
        if ($totalPuzzles > 0 && $entry->puzzles_completed >= $totalPuzzles) {
            $achievement = $this->award($user, 'first_contest');
            if ($achievement) {
                $earned[] = $achievement;
            }
        }

        // Meta Mind: solved first meta answer
        if ($entry->meta_solved) {
            $achievement = $this->award($user, 'first_meta_solve');
            if ($achievement) {
                $earned[] = $achievement;
            }
        }

        // Champion: finished 1st place (checked on contest end)
        if ($entry->rank === 1 && $entry->contest->hasEnded()) {
            $achievement = $this->award($user, 'contest_winner');
            if ($achievement) {
                $earned[] = $achievement;
            }
        }

        return $earned;
    }

    /**
     * Award an achievement if not already earned.
     */
    private function award(User $user, string $type): ?Achievement
    {
        if (! isset(self::DEFINITIONS[$type])) {
            return null;
        }

        $existing = Achievement::where('user_id', $user->id)->where('type', $type)->first();

        if ($existing) {
            return null;
        }

        $def = self::DEFINITIONS[$type];

        return Achievement::create([
            'user_id' => $user->id,
            'type' => $type,
            'label' => $def['label'],
            'description' => $def['description'],
            'icon' => $def['icon'],
            'earned_at' => now(),
        ]);
    }
}
