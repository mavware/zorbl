<?php

use App\Services\GridNumberer;

beforeEach(function () {
    $this->numberer = new GridNumberer;
});

it('numbers an empty grid with no blocks', function () {
    $grid = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $result = $this->numberer->number($grid, 3, 3);

    // With no blocks, each row starts an across word, each column starts a down word
    // Row 0: cell (0,0) starts across (length 3) and down (length 3) -> #1
    // Row 0: cell (0,1) starts down (length 3) -> #2
    // Row 0: cell (0,2) starts down (length 3) -> #3
    // No more across words since rows 1 and 2 have no left-edge/block boundary that qualifies
    expect($result['grid'][0][0])->toBe(1)
        ->and($result['across'])->toHaveCount(3)
        ->and($result['across'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 3])
        ->and($result['down'])->toHaveCount(3)
        ->and($result['down'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 3])
        ->and($result['down'][1])->toMatchArray(['number' => 2, 'row' => 0, 'col' => 1, 'length' => 3])
        ->and($result['down'][2])->toMatchArray(['number' => 3, 'row' => 0, 'col' => 2, 'length' => 3]);
});

it('numbers a grid with blocks correctly', function () {
    // Standard 3x3 mini crossword layout:
    // [1] [2] [#]
    // [3] [ ] [4]
    // [#] [5] [ ]
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        ['#', 0, 0],
    ];

    $result = $this->numberer->number($grid, 3, 3);

    expect($result['grid'])->toBe([
        [1, 2, '#'],
        [3, 0, 4],
        ['#', 5, 0],
    ]);

    expect($result['across'])->toHaveCount(3)
        ->and($result['across'][0])->toMatchArray(['number' => 1, 'length' => 2])
        ->and($result['across'][1])->toMatchArray(['number' => 3, 'length' => 3])
        ->and($result['across'][2])->toMatchArray(['number' => 5, 'length' => 2]);

    expect($result['down'])->toHaveCount(3)
        ->and($result['down'][0])->toMatchArray(['number' => 1, 'length' => 2])
        ->and($result['down'][1])->toMatchArray(['number' => 2, 'length' => 3])
        ->and($result['down'][2])->toMatchArray(['number' => 4, 'length' => 2]);
});

it('handles a fully blocked row', function () {
    $grid = [
        [0, 0, 0],
        ['#', '#', '#'],
        [0, 0, 0],
    ];

    $result = $this->numberer->number($grid, 3, 3);

    expect($result['across'])->toHaveCount(2)
        ->and($result['down'])->toHaveCount(0);
});

it('does not number single-cell words', function () {
    $grid = [
        ['#', 0, '#'],
        [0, 0, 0],
        ['#', 0, '#'],
    ];

    $result = $this->numberer->number($grid, 3, 3);

    // (0,1) starts down (length 3) -> #1
    // (1,0) starts across (length 3) -> #2
    expect($result['across'])->toHaveCount(1)
        ->and($result['across'][0])->toMatchArray(['number' => 2, 'row' => 1, 'col' => 0, 'length' => 3])
        ->and($result['down'])->toHaveCount(1)
        ->and($result['down'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 1, 'length' => 3]);
});

it('handles a 1x1 grid', function () {
    $grid = [[0]];

    $result = $this->numberer->number($grid, 1, 1);

    expect($result['across'])->toHaveCount(0)
        ->and($result['down'])->toHaveCount(0);
});

it('treats null cells as void (impassable) and preserves them', function () {
    // Diamond-like pattern: null cells at corners
    $grid = [
        [null, 0, null],
        [0,    0, 0],
        [null, 0, null],
    ];

    $result = $this->numberer->number($grid, 3, 3);

    // Null cells preserved as null in output
    expect($result['grid'][0][0])->toBeNull()
        ->and($result['grid'][0][2])->toBeNull()
        ->and($result['grid'][2][0])->toBeNull()
        ->and($result['grid'][2][2])->toBeNull();

    // (0,1) starts down (length 3) -> #1
    // (1,0) starts across (length 3) and down (no, single cell) -> #2
    // So: (1,0) starts across only -> #2
    expect($result['grid'][0][1])->toBe(1)
        ->and($result['grid'][1][0])->toBe(2);

    expect($result['across'])->toHaveCount(1)
        ->and($result['across'][0])->toMatchArray(['number' => 2, 'row' => 1, 'col' => 0, 'length' => 3]);

    expect($result['down'])->toHaveCount(1)
        ->and($result['down'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 1, 'length' => 3]);
});

it('numbers a diamond-shaped grid with null borders', function () {
    // 5x5 diamond shape
    $grid = [
        [null, null, 0,    null, null],
        [null, 0,    0,    0,    null],
        [0,    0,    0,    0,    0],
        [null, 0,    0,    0,    null],
        [null, null, 0,    null, null],
    ];

    $result = $this->numberer->number($grid, 5, 5);

    // All null cells stay null
    expect($result['grid'][0][0])->toBeNull()
        ->and($result['grid'][0][1])->toBeNull();

    // (0,2) starts down (length 5) -> #1
    // (1,1) starts across (length 3) and down (length 3) -> #2
    // (1,3) starts down (length 3) -> #3
    // (2,0) starts across (length 5) -> #4
    // (3,1) starts across (length 3) -> #5
    expect($result['across'])->toHaveCount(3)
        ->and($result['across'][0]['length'])->toBe(3)
        ->and($result['across'][1]['length'])->toBe(5)
        ->and($result['across'][2]['length'])->toBe(3);

    expect($result['down'])->toHaveCount(3)
        ->and($result['down'][0]['length'])->toBe(5)
        ->and($result['down'][1]['length'])->toBe(3)
        ->and($result['down'][2]['length'])->toBe(3);
});
