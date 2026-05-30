<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('your solver rank shows correct position', function () {
    $topSolver = User::factory()->create();
    PuzzleAttempt::factory()->count(10)->completed()->create(['user_id' => $topSolver->id]);

    $midSolver = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->completed()->create(['user_id' => $midSolver->id]);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(3)->completed()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard');

    $rank = $component->get('yourSolverRank');

    expect($rank)->not->toBeNull()
        ->and($rank['rank'])->toBe(3)
        ->and($rank['value'])->toBe(3);
});

test('your solver rank is null when user has no completed puzzles', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard');

    expect($component->get('yourSolverRank'))->toBeNull();
});

test('your solver rank card is hidden when user is in top 50', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->completed()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::leaderboard')
        ->assertDontSee('Your Rank');
});

test('your solver rank card is visible when user is outside top 50', function () {
    User::factory()->count(51)->create()->each(function ($u) {
        PuzzleAttempt::factory()->count(10)->completed()->create(['user_id' => $u->id]);
    });

    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(1)->completed()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::leaderboard')
        ->assertSee('Your Rank')
        ->assertSee('1 puzzle solved');
});

test('your speed rank shows correct position', function () {
    $fastSolver = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->completed()->create([
        'user_id' => $fastSolver->id,
        'solve_time_seconds' => 60,
    ]);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->completed()->create([
        'user_id' => $user->id,
        'solve_time_seconds' => 120,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'speed']);

    $rank = $component->get('yourSpeedRank');

    expect($rank)->not->toBeNull()
        ->and($rank['rank'])->toBe(2)
        ->and($rank['value'])->toBe(120)
        ->and($rank['solved_count'])->toBe(5);
});

test('your speed rank is null when user has fewer than 5 solves', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(4)->completed()->create([
        'user_id' => $user->id,
        'solve_time_seconds' => 100,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'speed']);

    expect($component->get('yourSpeedRank'))->toBeNull();
});

test('your constructor rank shows correct position', function () {
    $topConstructor = User::factory()->create();
    $topPuzzle = Crossword::factory()->published()->create(['user_id' => $topConstructor->id]);
    PuzzleAttempt::factory()->count(10)->completed()->create(['crossword_id' => $topPuzzle->id]);

    $user = User::factory()->create();
    $userPuzzle = Crossword::factory()->published()->create(['user_id' => $user->id]);
    PuzzleAttempt::factory()->count(3)->completed()->create(['crossword_id' => $userPuzzle->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'constructors']);

    $rank = $component->get('yourConstructorRank');

    expect($rank)->not->toBeNull()
        ->and($rank['rank'])->toBe(2)
        ->and($rank['value'])->toBe(3)
        ->and($rank['published_count'])->toBe(1);
});

test('your constructor rank is null when user has no published puzzles', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'constructors']);

    expect($component->get('yourConstructorRank'))->toBeNull();
});

test('your streak rank shows correct position', function () {
    User::factory()->create(['longest_streak' => 30, 'current_streak' => 10]);
    $user = User::factory()->create(['longest_streak' => 10, 'current_streak' => 5]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'streaks']);

    $rank = $component->get('yourStreakRank');

    expect($rank)->not->toBeNull()
        ->and($rank['rank'])->toBe(2)
        ->and($rank['longest'])->toBe(10)
        ->and($rank['current'])->toBe(5);
});

test('your streak rank is null when user has no streak', function () {
    $user = User::factory()->create(['longest_streak' => 0, 'current_streak' => 0]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'streaks']);

    expect($component->get('yourStreakRank'))->toBeNull();
});

test('your rank card shows solve today button for streaks', function () {
    User::factory()->count(51)->create(['longest_streak' => 100, 'current_streak' => 50]);

    $user = User::factory()->create(['longest_streak' => 5, 'current_streak' => 2]);

    Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'streaks'])
        ->assertSee('Your Rank')
        ->assertSee('Solve today');
});

test('your rank card shows solve more button for solvers', function () {
    User::factory()->count(51)->create()->each(function ($u) {
        PuzzleAttempt::factory()->count(10)->completed()->create(['user_id' => $u->id]);
    });

    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(1)->completed()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::leaderboard')
        ->assertSee('Solve more');
});

test('your rank card shows build more button for constructors outside top 50', function () {
    User::factory()->count(51)->create()->each(function ($u) {
        $puzzle = Crossword::factory()->published()->create(['user_id' => $u->id]);
        PuzzleAttempt::factory()->count(10)->completed()->create(['crossword_id' => $puzzle->id]);
    });

    $user = User::factory()->create();
    Crossword::factory()->published()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::leaderboard', ['tab' => 'constructors'])
        ->assertSee('Build more');
});

test('your solver rank handles tie correctly', function () {
    $other = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->completed()->create(['user_id' => $other->id]);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->completed()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::leaderboard');

    $rank = $component->get('yourSolverRank');

    expect($rank['rank'])->toBe(1)
        ->and($rank['value'])->toBe(5);
});
