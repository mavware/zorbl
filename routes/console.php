<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('simulate:activity')->everyTenMinutes();
Schedule::command('contests:publish-scheduled')->everyMinute();
Schedule::command('contests:process-ended')->everyMinute();
