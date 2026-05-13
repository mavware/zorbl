<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('creating a puzzle attempt updates cached stats', function () {
    $crossword = Crossword::factory()->published()->create();
    $user = User::factory()->create();

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => false,
    ]);

    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(1)
        ->and($crossword->cached_completed_count)->toBe(0)
        ->and($crossword->cached_avg_solve_time)->toBeNull();
});

test('completing an attempt updates cached stats with solve time', function () {
    $crossword = Crossword::factory()->published()->create();
    $user = User::factory()->create();

    PuzzleAttempt::factory()->completed()->for($user)->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
    ]);

    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(1)
        ->and($crossword->cached_completed_count)->toBe(1)
        ->and($crossword->cached_avg_solve_time)->toBe(300);
});

test('multiple attempts calculate correct average solve time', function () {
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 200,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 400,
    ]);

    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(2)
        ->and($crossword->cached_completed_count)->toBe(2)
        ->and($crossword->cached_avg_solve_time)->toBe(300);
});

test('deleting an attempt updates cached stats', function () {
    $crossword = Crossword::factory()->published()->create();

    $attempt = PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
    ]);

    $crossword->refresh();
    expect($crossword->cached_attempts_count)->toBe(1);

    $attempt->delete();
    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(0)
        ->and($crossword->cached_completed_count)->toBe(0)
        ->and($crossword->cached_avg_solve_time)->toBeNull();
});

test('updating an attempt to completed updates cached stats', function () {
    $crossword = Crossword::factory()->published()->create();
    $user = User::factory()->create();

    $attempt = PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => false,
    ]);

    $crossword->refresh();
    expect($crossword->cached_completed_count)->toBe(0);

    $attempt->update([
        'is_completed' => true,
        'solve_time_seconds' => 180,
        'completed_at' => now(),
    ]);

    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(1)
        ->and($crossword->cached_completed_count)->toBe(1)
        ->and($crossword->cached_avg_solve_time)->toBe(180);
});

test('refreshSolveStats recalculates from scratch', function () {
    $crossword = Crossword::factory()->published()->create([
        'cached_attempts_count' => 99,
        'cached_completed_count' => 99,
        'cached_avg_solve_time' => 9999,
    ]);

    $crossword->refreshSolveStats();
    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(0)
        ->and($crossword->cached_completed_count)->toBe(0)
        ->and($crossword->cached_avg_solve_time)->toBeNull();
});

test('artisan crosswords:refresh-stats recalculates all published puzzles', function () {
    $crossword = Crossword::factory()->published()->create([
        'cached_attempts_count' => 0,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 600,
    ]);

    // Reset cached values to simulate stale data
    $crossword->updateQuietly([
        'cached_attempts_count' => 0,
        'cached_completed_count' => 0,
        'cached_avg_solve_time' => null,
    ]);

    $this->artisan('crosswords:refresh-stats')
        ->assertSuccessful();

    $crossword->refresh();

    expect($crossword->cached_attempts_count)->toBe(1)
        ->and($crossword->cached_completed_count)->toBe(1)
        ->and($crossword->cached_avg_solve_time)->toBe(600);
});

test('averageSolveTimeSeconds returns cached value', function () {
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 450,
    ]);

    $crossword->refresh();

    expect($crossword->averageSolveTimeSeconds())->toBe(450);
});
