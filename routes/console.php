<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('simulate:activity')->everyTenMinutes();
Schedule::command('contests:publish-scheduled')->everyMinute()->when(fn (): bool => (bool) config('crosswordbuilder.features.contests'));
Schedule::command('contests:process-ended')->everyMinute()->when(fn (): bool => (bool) config('crosswordbuilder.features.contests'));
Schedule::command('constructors:send-weekly-digest')->weeklyOn(1, '9:00');
Schedule::command('clues:backfill')->hourly()->withoutOverlapping()->when(fn (): bool => (bool) config('crosswordbuilder.features.clue_backfill'));
// ~10k Wiktionary entries a day: a full pass through English lemmas takes about three months.
Schedule::command('words:import-wiktionary --pages=20')->dailyAt('03:00')->withoutOverlapping();
// Yesterday's puzzles from Newsday and the Guardian, pending review; a few per run so a missed day catches up.
Schedule::command('clues:scrape-published')->dailyAt('05:00')->withoutOverlapping();

// Database housekeeping. users:prune-anonymous is scheduled in bootstrap/app.php.
Schedule::command('model:prune')->dailyAt('04:00');
Schedule::command('notifications:prune')->dailyAt('04:10');
Schedule::command('queue:prune-failed --hours=168')->dailyAt('04:20');
Schedule::command('queue:prune-batches --hours=48')->dailyAt('04:20');
Schedule::command('auth:clear-resets')->dailyAt('04:20');
Schedule::command('cache:prune-expired')->hourly();
