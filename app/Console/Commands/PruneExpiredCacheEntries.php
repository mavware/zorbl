<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('cache:prune-expired')]
#[Description('Delete expired rows from the database cache store')]
class PruneExpiredCacheEntries extends Command
{
    /**
     * The database cache driver only drops an expired row when that key is
     * read again. One-off keys (rate limiter buckets per IP, locks, per-user
     * caches for visitors who never return) therefore pile up forever.
     */
    public function handle(): int
    {
        $store = config('cache.default');
        $driver = config("cache.stores.{$store}.driver");

        if ($driver !== 'database') {
            $this->info("Cache store [{$store}] uses the {$driver} driver; nothing to prune.");

            return self::SUCCESS;
        }

        $connection = config("cache.stores.{$store}.connection");
        $table = config("cache.stores.{$store}.table", 'cache');
        $now = now()->getTimestamp();

        $deleted = DB::connection($connection)->table($table)->where('expiration', '<=', $now)->delete();

        $this->info("Pruned {$deleted} expired cache entr".($deleted === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
