<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:prune-anonymous {--days=30 : Anonymous accounts older than this many days are deleted}')]
#[Description('Delete stale anonymous "guest builder" accounts and cascade their puzzles')]
class PruneAnonymousUsers extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays(max($days, 1));

        $deleted = 0;

        User::query()
            ->where('is_anonymous', true)
            ->where('anonymous_created_at', '<', $cutoff)
            ->chunkById(500, function ($users) use (&$deleted): void {
                foreach ($users as $user) {
                    $user->delete();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} anonymous user(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
