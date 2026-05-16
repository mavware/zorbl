<?php

use App\Enums\PuzzleType;
use App\Models\Crossword;

test('displayTitle returns user-supplied title when present', function () {
    $crossword = Crossword::factory()->create(['title' => 'My Great Puzzle']);

    expect($crossword->displayTitle())->toBe('My Great Puzzle');
});

test('displayTitle generates fallback for standard puzzle without title', function () {
    $crossword = Crossword::factory()->create([
        'title' => null,
        'width' => 15,
        'height' => 15,
        'puzzle_type' => PuzzleType::Standard,
    ]);

    expect($crossword->displayTitle())->toBe('15×15 Standard Crossword');
});

test('displayTitle generates fallback for diamond puzzle without title', function () {
    $crossword = Crossword::factory()->create([
        'title' => null,
        'width' => 11,
        'height' => 11,
        'puzzle_type' => PuzzleType::Diamond,
        'grid' => PuzzleType::Diamond->generateGrid(11, 11),
    ]);

    expect($crossword->displayTitle())->toBe('11×11 Diamond Crossword');
});

test('displayTitle generates fallback for freestyle puzzle without title', function () {
    $crossword = Crossword::factory()->create([
        'title' => null,
        'width' => 7,
        'height' => 7,
        'puzzle_type' => PuzzleType::Freestyle,
    ]);

    expect($crossword->displayTitle())->toBe('7×7 Freestyle Crossword');
});

test('displayTitle generates fallback with empty string title', function () {
    $crossword = Crossword::factory()->create([
        'title' => '',
        'width' => 10,
        'height' => 10,
    ]);

    expect($crossword->displayTitle())->toBe('10×10 Standard Crossword');
});

test('displayTitle detects shaped puzzles in fallback', function () {
    $grid = Crossword::emptyGrid(5, 5);
    $grid[0][0] = null;
    $grid[4][4] = null;

    $crossword = Crossword::factory()->create([
        'title' => null,
        'width' => 5,
        'height' => 5,
        'puzzle_type' => PuzzleType::Standard,
        'grid' => $grid,
    ]);

    expect($crossword->displayTitle())->toBe('5×5 Shaped Crossword');
});
