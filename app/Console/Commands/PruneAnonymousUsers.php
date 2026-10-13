<?php

namespace App\Console\Commands;

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('users:prune-anonymous {--days=30 : Anonymous accounts not seen for this many days are deleted}')]
#[Description('Delete idle anonymous "guest builder" accounts and cascade their puzzles and attempts')]
class PruneAnonymousUsers extends Command
{
    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $cutoff = now()->subDays($days);

        $deleted = 0;

        User::query()
            ->where('is_anonymous', true)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('last_seen_at', '<', $cutoff)
                    // Rows that predate last-seen tracking fall back to their creation date.
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNull('last_seen_at')
                        ->where('anonymous_created_at', '<', $cutoff));
            })
            ->chunkById(500, function (Collection $users) use (&$deleted): void {
                $touchedCrosswordIds = $this->crosswordIdsSolvedBy($users);

                foreach ($users as $user) {
                    $user->delete();
                    $deleted++;
                }

                $this->refreshSolveStats($touchedCrosswordIds);
            });

        $this->info("Pruned {$deleted} anonymous user(s) not seen for {$days} day(s).");

        return self::SUCCESS;
    }

    /**
     * Crosswords that will lose attempts when these users go. The FK cascade
     * skips the PuzzleAttempt observer, so their cached solve stats must be
     * refreshed by hand afterwards.
     *
     * @param  Collection<int, User>  $users
     * @return list<int>
     */
    private function crosswordIdsSolvedBy(Collection $users): array
    {
        return PuzzleAttempt::query()
            ->whereIn('user_id', $users->modelKeys())
            ->distinct()
            ->pluck('crossword_id')
            ->all();
    }

    /**
     * @param  list<int>  $crosswordIds
     */
    private function refreshSolveStats(array $crosswordIds): void
    {
        if ($crosswordIds === []) {
            return;
        }

        // Puzzles owned by the pruned guests are gone too, so only the survivors load.
        Crossword::query()
            ->whereIn('id', $crosswordIds)
            ->each(fn (Crossword $crossword) => $crossword->refreshSolveStats());
    }
}
