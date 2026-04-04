<?php

namespace App\Console\Commands;

use App\Models\Contest;
use App\Services\AchievementService;
use App\Services\ContestService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contests:process-ended')]
#[Description('Transition active contests past their end time to ended status and finalize leaderboards')]
class ProcessEndedContests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ContestService $contestService, AchievementService $achievementService): int
    {
        $contests = Contest::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        if ($contests->isEmpty()) {
            $this->info('No contests to process.');

            return self::SUCCESS;
        }

        foreach ($contests as $contest) {
            $contest->update(['status' => 'ended']);

            $contestService->recalculateLeaderboard($contest);

            // Award contest_winner achievement to rank 1 entry
            $winner = $contest->entries()->where('rank', 1)->with('user')->first();
            if ($winner) {
                $achievementService->checkContestAchievements($winner->user, $winner);
            }

            $this->info("Processed contest: {$contest->title}");
        }

        $this->info("Processed {$contests->count()} contest(s).");

        return self::SUCCESS;
    }
}
