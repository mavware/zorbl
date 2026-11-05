<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    app()->instance('env', 'testing');
});

function simulateActivityScheduleEvent(): object
{
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'simulate:activity'));

    expect($events)->toHaveCount(1);

    return $events->first();
}

test('the activity simulator is scheduled every ten minutes for local only', function () {
    $event = simulateActivityScheduleEvent();

    expect($event->expression)->toBe('*/10 * * * *');

    expect($event->runsInEnvironment('local'))->toBeTrue()
        ->and($event->runsInEnvironment('production'))->toBeFalse()
        ->and($event->runsInEnvironment('staging'))->toBeFalse();
});

test('the activity simulator refuses to run in production', function () {
    app()->instance('env', 'production');

    $this->artisan('simulate:activity')
        ->expectsOutputToContain('disabled in production')
        ->assertFailed();
});

test('the activity simulator still runs outside production', function () {
    $this->artisan('simulate:activity')
        ->expectsOutputToContain('No seeded users or crosswords found')
        ->assertFailed();
});
