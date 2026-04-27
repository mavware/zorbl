<?php

use App\Enums\PuzzleType;
use App\Models\Crossword;

test('standard puzzle with no voids or bars returns Standard', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'grid' => [[1, 2], [3, 0]],
        'styles' => null,
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Standard');
});

test('standard puzzle with null cells returns Shaped', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'grid' => [[1, null], [3, 0]],
        'styles' => null,
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Shaped');
});

test('standard puzzle with bars returns Barred', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'grid' => [[1, 2], [3, 0]],
        'styles' => [
            ['bars' => ['right']],
            [],
            [],
            [],
        ],
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Barred');
});

test('shaped takes priority over barred when both present', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'grid' => [[1, null], [3, 0]],
        'styles' => [
            ['bars' => ['right']],
            [],
            [],
            [],
        ],
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Shaped');
});

test('diamond puzzle returns Diamond', function () {
    $crossword = Crossword::factory()->diamond()->create();

    expect($crossword->puzzleTypeLabel())->toBe('Diamond');
});

test('freestyle puzzle returns Freestyle', function () {
    $crossword = Crossword::factory()->freestyle()->create();

    expect($crossword->puzzleTypeLabel())->toBe('Freestyle');
});

test('standard puzzle with empty bars array returns Standard', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'grid' => [[1, 2], [3, 0]],
        'styles' => [
            ['bars' => []],
            [],
        ],
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Standard');
});

test('standard puzzle with block cells returns Standard', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'grid' => [[1, '#'], [2, 0]],
        'styles' => null,
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Standard');
});
