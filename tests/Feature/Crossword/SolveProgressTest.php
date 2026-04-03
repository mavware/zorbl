<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('solve progress is 0 for an empty attempt', function () {
    $crossword = Crossword::factory()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [0, 0, 0], [0, 0, 0]],
    ]);

    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => Crossword::emptySolution(3, 3),
    ]);

    expect($attempt->solveProgress())->toBe(0);
});

test('solve progress is 100 for a fully filled attempt', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    expect($attempt->solveProgress())->toBe(100);
});

test('solve progress calculates partial fill correctly', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['A', 'B'], ['', '']],
    ]);

    expect($attempt->solveProgress())->toBe(50);
});

test('solve progress ignores black cells', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, '#'], [2, 0]],
    ]);

    // 3 fillable cells (1, 2, 0), '#' is a block
    // 2 filled = 67%
    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['A', ''], ['B', '']],
    ]);

    expect($attempt->solveProgress())->toBe(67);
});

test('solve progress ignores void cells', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, null], [2, 0]],
    ]);

    // 3 fillable cells (1, 2, 0), null is void
    // 1 filled = 33%
    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['A', ''], ['', '']],
    ]);

    expect($attempt->solveProgress())->toBe(33);
});

test('solve progress is 0 when grid has no fillable cells', function () {
    $crossword = Crossword::factory()->create([
        'width' => 2,
        'height' => 1,
        'grid' => [['#', '#']],
    ]);

    $attempt = PuzzleAttempt::factory()->for($crossword)->create([
        'progress' => [['', '']],
    ]);

    expect($attempt->solveProgress())->toBe(0);
});

test('solve progress displays on solving index page', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => [['A', 'B'], ['', '']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSee('50%');
});

test('completed attempt shows 100% progress', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSee('100%');
});
