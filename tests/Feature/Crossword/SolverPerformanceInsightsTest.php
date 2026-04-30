<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

// --- Crossword::averageSolveTimeSeconds ---

test('averageSolveTimeSeconds returns null when no completed attempts', function () {
    $crossword = Crossword::factory()->published()->create();

    expect($crossword->averageSolveTimeSeconds())->toBeNull();
});

test('averageSolveTimeSeconds returns null for incomplete attempts', function () {
    $crossword = Crossword::factory()->published()->create();
    PuzzleAttempt::factory()->create([
        'crossword_id' => $crossword->id,
        'is_completed' => false,
        'solve_time_seconds' => 300,
    ]);

    expect($crossword->averageSolveTimeSeconds())->toBeNull();
});

test('averageSolveTimeSeconds computes average across completed attempts', function () {
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 200,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 400,
    ]);

    expect($crossword->averageSolveTimeSeconds())->toBe(300);
});

test('averageSolveTimeSeconds ignores attempts with null solve time', function () {
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 600,
    ]);
    PuzzleAttempt::factory()->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'solve_time_seconds' => null,
    ]);

    expect($crossword->averageSolveTimeSeconds())->toBe(600);
});

// --- PuzzleAttempt::fasterThanPercent ---

test('fasterThanPercent returns null for incomplete attempt', function () {
    $attempt = PuzzleAttempt::factory()->create([
        'is_completed' => false,
        'solve_time_seconds' => 100,
    ]);

    expect($attempt->fasterThanPercent())->toBeNull();
});

test('fasterThanPercent returns null when no other solvers', function () {
    $attempt = PuzzleAttempt::factory()->completed()->create([
        'solve_time_seconds' => 100,
    ]);

    expect($attempt->fasterThanPercent())->toBeNull();
});

test('fasterThanPercent returns 100 when fastest solver', function () {
    $crossword = Crossword::factory()->published()->create();

    $fast = PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 100,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 500,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 800,
    ]);

    expect($fast->fasterThanPercent())->toBe(100);
});

test('fasterThanPercent returns 0 when slowest solver', function () {
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 100,
    ]);
    $slow = PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 800,
    ]);

    expect($slow->fasterThanPercent())->toBe(0);
});

// --- Dashboard progress bars ---

test('dashboard in-progress attempts include progress data for progress bars', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[0, 0, 0], [0, 0, 0], [0, 0, 0]],
        'solution' => [['A', 'B', 'C'], ['D', 'E', 'F'], ['G', 'H', 'I']],
    ]);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => false,
        'progress' => [['A', 'B', ''], ['D', '', ''], ['', '', '']],
    ]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');
    $attempts = $component->get('inProgressAttempts');

    expect($attempts)->toHaveCount(1);
    $progress = $attempts->first()->solveProgress();
    expect($progress)->toBe(33);
});

// --- Stats page community comparison ---

test('stats page shows community averages for completed puzzles', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 200,
        'completed_at' => now(),
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 400,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 600,
    ]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.stats');

    $averages = $component->get('communityAverages');
    expect($averages)->toHaveKey($crossword->id);
    expect($averages[$crossword->id]['avg_time'])->toBe(400);
    expect($averages[$crossword->id]['solver_count'])->toBe(3);
});

test('stats page faster than average count is correct', function () {
    $user = User::factory()->create();

    $fast = Crossword::factory()->published()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $fast->id,
        'solve_time_seconds' => 100,
        'completed_at' => now(),
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $fast->id,
        'solve_time_seconds' => 500,
    ]);

    $slow = Crossword::factory()->published()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $slow->id,
        'solve_time_seconds' => 900,
        'completed_at' => now(),
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $slow->id,
        'solve_time_seconds' => 200,
    ]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.stats');

    expect($component->get('fasterThanAverageCount'))->toBe(1);
    expect($component->get('puzzlesWithCommunityData'))->toBe(2);
});

test('stats page shows vs avg column in solve history', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create(['title' => 'Test Puzzle']);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 100,
        'completed_at' => now(),
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 500,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.stats')
        ->assertSee('vs. Avg')
        ->assertSee('faster');
});

test('stats page hides vs avg when only one solver', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create(['title' => 'Solo Puzzle']);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
        'completed_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.stats')
        ->assertSee('Solo Puzzle')
        ->assertDontSee('faster')
        ->assertDontSee('slower');
});

// --- Solver page performance comparison ---

test('solver page shows performance comparison when puzzle is completed with community data', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[0, 0, 0], [0, 0, 0], [0, 0, 0]],
        'solution' => [['A', 'B', 'C'], ['D', 'E', 'F'], ['G', 'H', 'I']],
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 600,
    ]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solver', ['crossword' => $crossword->id]);
    $stats = $component->get('communityStats');

    expect($stats)->not->toBeNull();
    expect($stats['your_time'])->toBe(120);
    expect($stats['avg_time'])->toBe(360);
    expect($stats['diff'])->toBeLessThan(0);
    expect($stats['percentile'])->toBe(100);
});

test('solver page hides performance comparison when only solver', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[0, 0, 0], [0, 0, 0], [0, 0, 0]],
        'solution' => [['A', 'B', 'C'], ['D', 'E', 'F'], ['G', 'H', 'I']],
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solver', ['crossword' => $crossword->id]);

    expect($component->get('communityStats'))->toBeNull();
});
