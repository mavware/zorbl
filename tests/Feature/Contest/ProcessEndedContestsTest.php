<?php

use App\Models\Achievement;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\User;

// --- Basic transition behavior ---

test('process-ended command transitions active contests past their end time to ended', function () {
    $contest = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);

    $this->artisan('contests:process-ended')
        ->assertSuccessful()
        ->expectsOutputToContain("Processed contest: {$contest->title}");

    expect($contest->fresh()->status)->toBe('ended');
});

test('process-ended command reports count of processed contests', function () {
    Contest::factory()->count(3)->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);

    $this->artisan('contests:process-ended')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed 3 contest(s).');
});

test('process-ended command reports no contests when none are ready', function () {
    Contest::factory()->active()->create();

    $this->artisan('contests:process-ended')
        ->assertSuccessful()
        ->expectsOutputToContain('No contests to process.');
});

test('process-ended command ignores active contests that have not ended yet', function () {
    $contest = Contest::factory()->active()->create();

    $this->artisan('contests:process-ended')
        ->assertSuccessful();

    expect($contest->fresh()->status)->toBe('active');
});

test('process-ended command ignores non-active contests', function () {
    $draft = Contest::factory()->draft()->create([
        'ends_at' => now()->subHour(),
    ]);
    $upcoming = Contest::factory()->upcoming()->create([
        'ends_at' => now()->subHour(),
    ]);
    $ended = Contest::factory()->ended()->create();

    $this->artisan('contests:process-ended')
        ->assertSuccessful()
        ->expectsOutputToContain('No contests to process.');

    expect($draft->fresh()->status)->toBe('draft')
        ->and($upcoming->fresh()->status)->toBe('upcoming')
        ->and($ended->fresh()->status)->toBe('ended');
});

// --- Leaderboard recalculation ---

test('process-ended recalculates leaderboard rankings', function () {
    $contest = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);

    $first = ContestEntry::factory()->for($contest)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHours(3),
        'total_solve_time_seconds' => 300,
    ]);
    $second = ContestEntry::factory()->for($contest)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHours(2),
        'total_solve_time_seconds' => 400,
    ]);
    $third = ContestEntry::factory()->for($contest)->create([
        'meta_solved' => false,
        'total_solve_time_seconds' => 200,
    ]);

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect($first->fresh()->rank)->toBe(1)
        ->and($second->fresh()->rank)->toBe(2)
        ->and($third->fresh()->rank)->toBe(3);
});

test('process-ended works correctly for contest with no entries', function () {
    $contest = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect($contest->fresh()->status)->toBe('ended');
});

// --- Winner achievement ---

test('process-ended awards contest_winner achievement to rank 1 entry', function () {
    $contest = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);
    $crossword = Crossword::factory()->published()->create();
    $contest->crosswords()->attach($crossword->id);

    $winner = User::factory()->create();
    ContestEntry::factory()->for($contest)->for($winner)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHours(3),
        'puzzles_completed' => 1,
        'total_solve_time_seconds' => 300,
    ]);

    $runnerUp = User::factory()->create();
    ContestEntry::factory()->for($contest)->for($runnerUp)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHours(2),
        'puzzles_completed' => 1,
        'total_solve_time_seconds' => 500,
    ]);

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect(Achievement::where('user_id', $winner->id)->where('type', 'contest_winner')->exists())->toBeTrue()
        ->and(Achievement::where('user_id', $runnerUp->id)->where('type', 'contest_winner')->exists())->toBeFalse();
});

test('process-ended does not duplicate contest_winner achievement if already earned', function () {
    $contest = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);

    $winner = User::factory()->create();
    ContestEntry::factory()->for($contest)->for($winner)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHours(3),
        'total_solve_time_seconds' => 300,
    ]);

    Achievement::create([
        'user_id' => $winner->id,
        'type' => 'contest_winner',
        'label' => 'Champion',
        'description' => 'Finished 1st place in a contest',
        'icon' => 'trophy',
        'earned_at' => now()->subWeek(),
    ]);

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect(Achievement::where('user_id', $winner->id)->where('type', 'contest_winner')->count())->toBe(1);
});

test('process-ended does not award achievement when no entries exist', function () {
    Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHour(),
    ]);

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect(Achievement::count())->toBe(0);
});

// --- Multiple contests ---

test('process-ended handles multiple contests in a single run', function () {
    $contest1 = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subHours(2),
    ]);
    $contest2 = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(5),
        'ends_at' => now()->subMinutes(30),
    ]);
    $stillActive = Contest::factory()->active()->create();

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect($contest1->fresh()->status)->toBe('ended')
        ->and($contest2->fresh()->status)->toBe('ended')
        ->and($stillActive->fresh()->status)->toBe('active');
});

// --- Edge cases ---

test('process-ended handles contest ending exactly now', function () {
    $contest = Contest::factory()->create([
        'status' => 'active',
        'starts_at' => now()->subDays(8),
        'ends_at' => now(),
    ]);

    $this->artisan('contests:process-ended')->assertSuccessful();

    expect($contest->fresh()->status)->toBe('ended');
});
