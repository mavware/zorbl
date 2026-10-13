<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

#[Signature('notifications:prune {--days=90 : Read notifications older than this many days are deleted}')]
#[Description('Delete database notifications the recipient has already read')]
class PruneReadNotifications extends Command
{
    public function handle(): int
    {
        $days = max((int) $this->option('days'), 1);
        $cutoff = now()->subDays($days);

        $deleted = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} read notification(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
