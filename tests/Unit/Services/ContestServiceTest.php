<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Services\ContestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('register creates a new entry for the user', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $service = app(ContestService::class);

    $entry = $service->register($user, $contest);

    expect($entry)->toBeInstanceOf(ContestEntry::class)
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->contest_id)->toBe($contest->id)
        ->and($entry->registered_at)->not->toBeNull();
});

test('register returns existing entry if already registered', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $service = app(ContestService::class);

    $entry1 = $service->register($user, $contest);
    $entry2 = $service->register($user, $contest);

    expect($entry1->id)->toBe($entry2->id)
        ->and(ContestEntry::where('user_id', $user->id)->count())->toBe(1);
});

test('submitMetaAnswer returns true for correct answer', function () {
    $contest = Contest::factory()->active()->create(['meta_answer' => 'ANSWER']);
    $entry = ContestEntry::factory()->for($contest)->create();
    $service = app(ContestService::class);

    $result = $service->submitMetaAnswer($entry, 'answer');

    expect($result)->toBeTrue()
        ->and($entry->fresh()->meta_solved)->toBeTrue()
        ->and($entry->fresh()->meta_attempts_count)->toBe(1);
});

test('submitMetaAnswer returns false for incorrect answer', function () {
    $contest = Contest::factory()->active()->create(['meta_answer' => 'ANSWER']);
    $entry = ContestEntry::factory()->for($contest)->create();
    $service = app(ContestService::class);

    $result = $service->submitMetaAnswer($entry, 'wrong');

    expect($result)->toBeFalse()
        ->and($entry->fresh()->meta_solved)->toBeFalse()
        ->and($entry->fresh()->meta_attempts_count)->toBe(1);
});

test('leaderboard ranks meta solvers first then by submission time', function () {
    $contest = Contest::factory()->active()->create();
    $service = app(ContestService::class);

    // Entry 1: solved meta later
    $entry1 = ContestEntry::factory()->for($contest)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHour(),
        'total_solve_time_seconds' => 600,
    ]);

    // Entry 2: solved meta earlier
    $entry2 = ContestEntry::factory()->for($contest)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now()->subHours(2),
        'total_solve_time_seconds' => 900,
    ]);

    // Entry 3: hasn't solved meta
    $entry3 = ContestEntry::factory()->for($contest)->create([
        'meta_solved' => false,
        'total_solve_time_seconds' => 300,
    ]);

    $service->recalculateLeaderboard($contest);

    expect($entry2->fresh()->rank)->toBe(1)
        ->and($entry1->fresh()->rank)->toBe(2)
        ->and($entry3->fresh()->rank)->toBe(3);
});

test('syncPuzzleCompletion calculates from puzzle attempts', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $crossword1 = Crossword::factory()->published()->create();
    $crossword2 = Crossword::factory()->published()->create();
    $contest->crosswords()->attach([$crossword1->id, $crossword2->id]);

    $entry = ContestEntry::factory()->for($contest)->for($user)->create();

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword1->id,
        'is_completed' => true,
        'solve_time_seconds' => 120,
    ]);

    $service = app(ContestService::class);
    $service->syncPuzzleCompletion($entry);

    expect($entry->fresh()->puzzles_completed)->toBe(1)
        ->and($entry->fresh()->total_solve_time_seconds)->toBe(120);
});
