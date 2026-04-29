<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('leaderboard shows completed solvers ordered by time', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $fast = User::factory()->create(['name' => 'Fast Solver']);
    $medium = User::factory()->create(['name' => 'Medium Solver']);
    $slow = User::factory()->create(['name' => 'Slow Solver']);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $fast->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $medium->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $slow->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
    ]);

    $this->actingAs($medium);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    $leaderboard = $component->get('leaderboard');

    expect($leaderboard)->toHaveCount(3)
        ->and($leaderboard[0]['name'])->toBe('Fast Solver')
        ->and($leaderboard[0]['seconds'])->toBe(60)
        ->and($leaderboard[1]['name'])->toBe('Medium Solver')
        ->and($leaderboard[2]['name'])->toBe('Slow Solver');
});

test('leaderboard limits to top 10 solvers', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $users = User::factory()->count(12)->create();
    foreach ($users as $i => $user) {
        PuzzleAttempt::factory()->completed()->create([
            'user_id' => $user->id,
            'crossword_id' => $crossword->id,
            'solve_time_seconds' => ($i + 1) * 30,
        ]);
    }

    $this->actingAs($users->first());

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    expect($component->get('leaderboard'))->toHaveCount(10);
});

test('leaderboard excludes incomplete attempts', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $completed = User::factory()->create(['name' => 'Completed']);
    $incomplete = User::factory()->create(['name' => 'Incomplete']);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $completed->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->create([
        'user_id' => $incomplete->id,
        'crossword_id' => $crossword->id,
        'is_completed' => false,
    ]);

    $this->actingAs($completed);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    $leaderboard = $component->get('leaderboard');

    expect($leaderboard)->toHaveCount(1)
        ->and($leaderboard[0]['name'])->toBe('Completed');
});

test('solver rank is calculated correctly', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $first->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $second->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $third->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
    ]);

    $this->actingAs($second);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    expect($component->get('solverRank'))->toBe(2)
        ->and($component->get('totalSolvers'))->toBe(3);
});

test('solver rank is null when puzzle not completed', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    expect($component->get('solverRank'))->toBeNull();
});

test('leaderboard is empty when no completed attempts exist', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    expect($component->get('leaderboard'))->toBeEmpty();
});

test('leaderboard entry includes formatted time', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 332,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);
    $leaderboard = $component->get('leaderboard');

    expect($leaderboard[0]['time'])->toBe('5:32');
});

test('leaderboard section renders when puzzle is solved with multiple solvers', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();
    $other = User::factory()->create();

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $other->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 60,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('Fastest Solvers')
        ->assertSee('Your rank:');
});

test('leaderboard section hidden when only one solver', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('Fastest Solvers');
});

test('leaderboard highlights current user with "You" label', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create(['name' => 'Test User']);
    $other = User::factory()->create(['name' => 'Other User']);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $other->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 60,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('You')
        ->assertSee('Other User');
});
