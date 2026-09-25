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
Schedule::command('words:export-json')->weekly()->withoutOverlapping();
Schedule::command('clues:backfill')->hourly()->withoutOverlapping()->when(fn (): bool => (bool) config('crosswordbuilder.features.clue_backfill'));
