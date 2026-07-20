<?php

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Notifications\SolverWeeklyDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── Command integration ──────────────────────────────────────────────────

test('digest is sent to solvers with activity in the past week', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(3),
        'completed_at' => now()->subDays(3),
    ]);

    $this->artisan('solvers:send-weekly-digest')
        ->expectsOutputToContain('Sent 1 digest(s)')
        ->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class);
});

test('digest is not sent to solvers with no activity', function () {
    Notification::fake();

    $solver = User::factory()->create();

    $this->artisan('solvers:send-weekly-digest')
        ->expectsOutputToContain('Sent 0')
        ->assertSuccessful();

    Notification::assertNotSentTo($solver, SolverWeeklyDigest::class);
});

test('digest skips activity older than the reporting window', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(10),
        'completed_at' => now()->subDays(10),
    ]);

    $this->artisan('solvers:send-weekly-digest')
        ->assertSuccessful();

    Notification::assertNotSentTo($solver, SolverWeeklyDigest::class);
});

test('digest includes puzzle completion count', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
    ]);

    $this->artisan('solvers:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class, function ($notification) {
        return $notification->stats['puzzles_completed'] === 1;
    });
});

test('digest includes total solve time', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
        'solve_time_seconds' => 300,
    ]);

    $this->artisan('solvers:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class, function ($notification) {
        return $notification->stats['total_solve_time_seconds'] === 300;
    });
});

test('digest identifies the fastest solved puzzle', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $fast = Crossword::factory()->published()->create(['title' => 'Fast Puzzle']);
    $slow = Crossword::factory()->published()->create(['title' => 'Slow Puzzle']);

    PuzzleAttempt::factory()->completed()->for($solver)->for($fast)->create([
        'created_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
        'solve_time_seconds' => 120,
    ]);

    PuzzleAttempt::factory()->completed()->for($solver)->for($slow)->create([
        'created_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
        'solve_time_seconds' => 600,
    ]);

    $this->artisan('solvers:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class, function ($notification) {
        return $notification->stats['best_puzzle']['title'] === 'Fast Puzzle'
            && $notification->stats['best_puzzle']['solve_time_seconds'] === 120;
    });
});

test('digest includes streak data from user model', function () {
    Notification::fake();

    $solver = User::factory()->create([
        'current_streak' => 5,
        'longest_streak' => 12,
    ]);
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('solvers:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class, function ($notification) {
        return $notification->stats['current_streak'] === 5
            && $notification->stats['longest_streak'] === 12;
    });
});

test('digest includes new puzzles available count', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'created_at' => now()->subDays(2),
    ]);

    PuzzleAttempt::factory()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(2),
    ]);

    Crossword::factory()->published()->create([
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('solvers:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class, function ($notification) {
        return $notification->stats['new_puzzles_available'] >= 2;
    });
});

test('custom --since flag narrows the reporting window', function () {
    Notification::fake();

    $solver = User::factory()->create();
    $oldCrossword = Crossword::factory()->published()->create();
    $recentCrossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($solver)->for($oldCrossword)->create([
        'created_at' => now()->subDays(5),
    ]);

    PuzzleAttempt::factory()->completed()->for($solver)->for($recentCrossword)->create([
        'created_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
    ]);

    $this->artisan('solvers:send-weekly-digest', [
        '--since' => now()->subDays(3)->toDateString(),
    ])->assertSuccessful();

    Notification::assertSentTo($solver, SolverWeeklyDigest::class, function ($notification) {
        return $notification->stats['puzzles_solved'] === 1;
    });
});

// ─── Notification preferences ─────────────────────────────────────────────

test('digest respects opt-out via notification preferences', function () {
    $solver = User::factory()->create([
        'notification_preferences' => [
            NotificationType::SolverWeeklyDigest->value => false,
        ],
    ]);

    $notification = new SolverWeeklyDigest([
        'puzzles_solved' => 5,
        'puzzles_completed' => 3,
        'total_solve_time_seconds' => 900,
        'current_streak' => 2,
        'longest_streak' => 7,
        'best_puzzle' => null,
        'new_puzzles_available' => 10,
    ]);

    expect($notification->via($solver))->toBe([]);
});

test('digest is sent when preference is enabled (default)', function () {
    $solver = User::factory()->create();

    $notification = new SolverWeeklyDigest([
        'puzzles_solved' => 5,
        'puzzles_completed' => 3,
        'total_solve_time_seconds' => 900,
        'current_streak' => 2,
        'longest_streak' => 7,
        'best_puzzle' => null,
        'new_puzzles_available' => 10,
    ]);

    expect($notification->via($solver))->toBe(['mail']);
});

// ─── Notification payload ─────────────────────────────────────────────────

test('digest email contains solving summary lines', function () {
    $solver = User::factory()->create(['name' => 'Alice']);

    $notification = new SolverWeeklyDigest([
        'puzzles_solved' => 8,
        'puzzles_completed' => 5,
        'total_solve_time_seconds' => 1800,
        'current_streak' => 3,
        'longest_streak' => 10,
        'best_puzzle' => ['title' => 'Quick Grid', 'solve_time_seconds' => 120],
        'new_puzzles_available' => 15,
    ]);

    $mail = $notification->toMail($solver);
    $rendered = $mail->render()->toHtml();

    expect($rendered)->toContain('5 puzzle(s) completed')
        ->and($rendered)->toContain('3 puzzle(s) started (in progress)')
        ->and($rendered)->toContain('30 min')
        ->and($rendered)->toContain('3-day solving streak')
        ->and($rendered)->toContain('Longest streak: 10 days')
        ->and($rendered)->toContain('Quick Grid')
        ->and($rendered)->toContain('15 new puzzle(s)');
});

test('digest email shows quiet-week message when no activity', function () {
    $solver = User::factory()->create(['name' => 'Bob']);

    $notification = new SolverWeeklyDigest([
        'puzzles_solved' => 0,
        'puzzles_completed' => 0,
        'total_solve_time_seconds' => 0,
        'current_streak' => 0,
        'longest_streak' => 0,
        'best_puzzle' => null,
        'new_puzzles_available' => 0,
    ]);

    $mail = $notification->toMail($solver);
    $rendered = $mail->render()->toHtml();

    expect($rendered)->toContain('quiet week');
});

test('digest is sent via mail channel only', function () {
    $solver = User::factory()->create();

    $notification = new SolverWeeklyDigest([
        'puzzles_solved' => 5,
        'puzzles_completed' => 3,
        'total_solve_time_seconds' => 900,
        'current_streak' => 2,
        'longest_streak' => 7,
        'best_puzzle' => null,
        'new_puzzles_available' => 10,
    ]);

    $channels = $notification->via($solver);

    expect($channels)->toBe(['mail']);
});

// ─── Notification preferences page ────────────────────────────────────────

test('solver weekly digest type appears on notification preferences page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee(NotificationType::SolverWeeklyDigest->label());
});
