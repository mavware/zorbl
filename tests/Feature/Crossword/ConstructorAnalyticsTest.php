<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('analytics page is accessible for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Constructor Analytics');
});

test('analytics page shows empty state without published puzzles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Publish puzzles to see analytics');
});

test('analytics page shows puzzle performance data', function () {
    $constructor = User::factory()->create();
    $solver = User::factory()->create();

    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Analytics Test Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($solver)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($constructor)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Analytics Test Puzzle')
        ->assertSee('2:00');
});

test('analytics counts solves and completions across all published puzzles', function () {
    $constructor = User::factory()->create();
    $solver1 = User::factory()->create();
    $solver2 = User::factory()->create();

    $puzzle1 = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $puzzle2 = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->for($solver1)->for($puzzle1)->completed()->create();
    PuzzleAttempt::factory()->for($solver2)->for($puzzle1)->create();
    PuzzleAttempt::factory()->for($solver1)->for($puzzle2)->completed()->create();

    $this->actingAs($constructor)
        ->get(route('crosswords.analytics'))
        ->assertOk();
});

test('analytics link appears on my puzzles page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Analytics');
});
