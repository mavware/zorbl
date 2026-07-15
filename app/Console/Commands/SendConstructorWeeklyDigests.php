<?php

namespace App\Console\Commands;

use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use App\Notifications\ConstructorWeeklyDigest;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('constructors:send-weekly-digest {--since= : Start of the reporting period (Y-m-d), defaults to 7 days ago}')]
#[Description('Send a weekly activity digest email to constructors with published puzzles')]
class SendConstructorWeeklyDigests extends Command
{
    public function handle(): int
    {
        $since = $this->option('since')
            ? Carbon::parse($this->option('since'))->startOfDay()
            : Carbon::now()->subWeek()->startOfDay();

        $constructors = User::whereHas('crosswords', fn ($q) => $q->where('is_published', true))->get();

        $sent = 0;
        $skipped = 0;

        foreach ($constructors as $constructor) {
            $stats = $this->gatherStats($constructor, $since);

            $hasActivity = $stats['new_solves'] > 0
                || $stats['new_completions'] > 0
                || $stats['new_likes'] > 0
                || $stats['new_comments'] > 0
                || $stats['new_followers'] > 0;

            if (! $hasActivity) {
                $skipped++;

                continue;
            }

            $constructor->notify(new ConstructorWeeklyDigest($stats));
            $sent++;
        }

        $this->info("Sent {$sent} digest(s), skipped {$skipped} constructor(s) with no activity.");

        return self::SUCCESS;
    }

    /**
     * @return array{new_solves: int, new_completions: int, new_likes: int, new_comments: int, new_followers: int, top_puzzle: array{title: string, solves: int}|null}
     */
    private function gatherStats(User $constructor, CarbonInterface $since): array
    {
        $puzzleIds = $constructor->crosswords()
            ->where('is_published', true)
            ->pluck('id');

        $newSolves = PuzzleAttempt::whereIn('crossword_id', $puzzleIds)
            ->where('created_at', '>=', $since)
            ->count();

        $newCompletions = PuzzleAttempt::whereIn('crossword_id', $puzzleIds)
            ->where('is_completed', true)
            ->where('completed_at', '>=', $since)
            ->count();

        $newLikes = CrosswordLike::whereIn('crossword_id', $puzzleIds)
            ->where('created_at', '>=', $since)
            ->count();

        $newComments = PuzzleComment::whereIn('crossword_id', $puzzleIds)
            ->where('created_at', '>=', $since)
            ->count();

        $newFollowers = Follow::where('following_id', $constructor->id)
            ->where('created_at', '>=', $since)
            ->count();

        $topPuzzle = null;

        if ($newSolves > 0) {
            $top = PuzzleAttempt::whereIn('crossword_id', $puzzleIds)
                ->where('created_at', '>=', $since)
                ->selectRaw('crossword_id, count(*) as solve_count')
                ->groupBy('crossword_id')
                ->orderByDesc('solve_count')
                ->first();

            if ($top) {
                $crossword = $constructor->crosswords()->find($top->crossword_id);
                $topPuzzle = [
                    'title' => $crossword?->displayTitle() ?? __('Untitled'),
                    'solves' => $top->solve_count,
                ];
            }
        }

        return [
            'new_solves' => $newSolves,
            'new_completions' => $newCompletions,
            'new_likes' => $newLikes,
            'new_comments' => $newComments,
            'new_followers' => $newFollowers,
            'top_puzzle' => $topPuzzle,
        ];
    }
}
