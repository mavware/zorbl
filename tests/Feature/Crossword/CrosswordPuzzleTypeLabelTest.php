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

test('a missing puzzle type is treated as Standard', function () {
    $crossword = new Crossword(['puzzle_type' => null, 'grid' => [[1, 2], [3, 0]]]);

    expect($crossword->puzzleTypeLabel())->toBe('Standard');
});

test('displayTitle works on a model loaded without the puzzle_type column', function () {
    $created = Crossword::factory()->create(['title' => null, 'width' => 15, 'height' => 15]);

    $partial = Crossword::query()->select(['id', 'title', 'width', 'height'])->findOrFail($created->id);

    expect($partial->displayTitle())->toBe('15×15 Standard Crossword');
});

test('displayTitle does not throw on a model loaded with only id and title', function () {
    $created = Crossword::factory()->create(['title' => null]);

    $bare = Crossword::query()->select(['id', 'title'])->findOrFail($created->id);

    expect($bare->displayTitle())->toContain('Standard Crossword');
});
