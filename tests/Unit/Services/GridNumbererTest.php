<?php

use Zorbl\CrosswordIO\GridNumberer;

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

it('uses bars as word boundaries for numbering', function () {
    // 3x3 grid with no blocks, vertical bar splitting after col 1:
    // [1] [2] | [3]
    // [4] [ ] | [ ]
    // [5] [ ] | [ ]
    $grid = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $styles = [
        '0,1' => ['bars' => ['right']],
        '1,1' => ['bars' => ['right']],
        '2,1' => ['bars' => ['right']],
    ];

    $result = $this->numberer->number($grid, 3, 3, $styles);

    // Each row starts an across word of length 2 at col 0 (col 0 is always left boundary)
    // Col 2 cells are single-cell across (len 1) so no across for them
    // (0,0): across(2) + down(3) → #1
    // (0,1): down(3) → #2
    // (0,2): down(3) → #3
    // (1,0): across(2) → #4
    // (2,0): across(2) → #5
    expect($result['across'])->toHaveCount(3)
        ->and($result['across'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 2])
        ->and($result['across'][1])->toMatchArray(['number' => 4, 'row' => 1, 'col' => 0, 'length' => 2])
        ->and($result['across'][2])->toMatchArray(['number' => 5, 'row' => 2, 'col' => 0, 'length' => 2]);

    expect($result['down'])->toHaveCount(3)
        ->and($result['down'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 3])
        ->and($result['down'][1])->toMatchArray(['number' => 2, 'row' => 0, 'col' => 1, 'length' => 3])
        ->and($result['down'][2])->toMatchArray(['number' => 3, 'row' => 0, 'col' => 2, 'length' => 3]);
});

it('uses bars on either side of a boundary', function () {
    // A left bar on cell (0,2) is equivalent to a right bar on cell (0,1)
    $grid = [
        [0, 0, 0, 0],
        [0, 0, 0, 0],
    ];

    $styles = [
        '0,2' => ['bars' => ['left']],
        '1,2' => ['bars' => ['left']],
    ];

    $result = $this->numberer->number($grid, 4, 2, $styles);

    // Row 0: (0,0) across(2)+down(2)=#1, (0,1) down(2)=#2, (0,2) across(2)+down(2)=#3, (0,3) down(2)=#4
    // Row 1: (1,0) across(2)=#5, (1,2) across(2)=#6
    expect($result['across'])->toHaveCount(4)
        ->and($result['across'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 2])
        ->and($result['across'][1])->toMatchArray(['number' => 3, 'row' => 0, 'col' => 2, 'length' => 2])
        ->and($result['across'][2])->toMatchArray(['number' => 5, 'row' => 1, 'col' => 0, 'length' => 2])
        ->and($result['across'][3])->toMatchArray(['number' => 6, 'row' => 1, 'col' => 2, 'length' => 2]);
});

it('numbers a barred grid with horizontal bars', function () {
    // 3x3 grid with horizontal bar after row 0:
    // [1] [2] [3]
    // ———————————
    // [4] [ ] [ ]
    // [ ] [ ] [ ]
    $grid = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $styles = [
        '0,0' => ['bars' => ['bottom']],
        '0,1' => ['bars' => ['bottom']],
        '0,2' => ['bars' => ['bottom']],
    ];

    $result = $this->numberer->number($grid, 3, 3, $styles);

    // Row 0: (0,0) starts across (3), down blocked by bar (single cell) → #1
    //   (0,1): down single cell, no. (0,2): same.
    // Row 1: (1,0) starts across (3) and down (2) → #2
    //   (1,1): starts down (2) → #3
    //   (1,2): starts down (2) → #4
    // Row 2: (2,0) starts across (3, left boundary at col 0) → #5
    expect($result['across'])->toHaveCount(3)
        ->and($result['across'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 3])
        ->and($result['across'][1])->toMatchArray(['number' => 2, 'row' => 1, 'col' => 0, 'length' => 3])
        ->and($result['across'][2])->toMatchArray(['number' => 5, 'row' => 2, 'col' => 0, 'length' => 3]);

    expect($result['down'])->toHaveCount(3)
        ->and($result['down'][0])->toMatchArray(['number' => 2, 'row' => 1, 'col' => 0, 'length' => 2])
        ->and($result['down'][1])->toMatchArray(['number' => 3, 'row' => 1, 'col' => 1, 'length' => 2])
        ->and($result['down'][2])->toMatchArray(['number' => 4, 'row' => 1, 'col' => 2, 'length' => 2]);
});

it('handles both bars and blocks in the same grid', function () {
    // Hybrid: block at (0,2), bar on right of (1,0)
    // [1] [2] [#]
    // [3] | [4] [5]
    // [ ]   [ ] [ ]
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $styles = [
        '1,0' => ['bars' => ['right']],
        '2,0' => ['bars' => ['right']],
    ];

    $result = $this->numberer->number($grid, 3, 3, $styles);

    // (0,0): starts across (len 2) + down (len 3) → #1
    // (0,1): starts down (len 3) → #2
    // (1,0): left boundary (col 0), right boundary (bar) → len 1, no across
    // (1,1): left boundary (bar on (1,0) right), starts across (len 2) → #3
    // (1,2): top boundary (block above), starts down (len 2) → #4
    // (2,0): left boundary (col 0), right boundary (bar) → len 1, no across
    // (2,1): left boundary (bar on (2,0) right), starts across (len 2) → #5
    expect($result['across'])->toHaveCount(3)
        ->and($result['across'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 2])
        ->and($result['across'][1])->toMatchArray(['number' => 3, 'row' => 1, 'col' => 1, 'length' => 2])
        ->and($result['across'][2])->toMatchArray(['number' => 5, 'row' => 2, 'col' => 1, 'length' => 2]);

    expect($result['down'])->toHaveCount(3)
        ->and($result['down'][0])->toMatchArray(['number' => 1, 'row' => 0, 'col' => 0, 'length' => 3])
        ->and($result['down'][1])->toMatchArray(['number' => 2, 'row' => 0, 'col' => 1, 'length' => 3])
        ->and($result['down'][2])->toMatchArray(['number' => 4, 'row' => 1, 'col' => 2, 'length' => 2]);
});

it('filters out words shorter than minLength', function () {
    // Standard 3x3 mini crossword:
    // [1] [2] [#]    across: 1-across (len 2), 3-across (len 3), 5-across (len 2)
    // [3] [ ] [4]    down: 1-down (len 2), 2-down (len 3), 4-down (len 2)
    // [#] [5] [ ]
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        ['#', 0, 0],
    ];

    // With minLength=3, only length-3 words should have clues
    $result = $this->numberer->number($grid, 3, 3, [], 3);

    // Only 3-across (len 3), 2-down (len 3) qualify
    expect($result['across'])->toHaveCount(1)
        ->and($result['across'][0])->toMatchArray(['length' => 3])
        ->and($result['down'])->toHaveCount(1)
        ->and($result['down'][0])->toMatchArray(['length' => 3]);

    // Cells that only started short words should be 0 (no clue number)
    // (0,0) started 1-across(2) and 1-down(2) — both too short, so 0
    expect($result['grid'][0][0])->toBe(0);
});

it('defaults minLength to 2 preserving backward compatibility', function () {
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        ['#', 0, 0],
    ];

    $withDefault = $this->numberer->number($grid, 3, 3);
    $withExplicit = $this->numberer->number($grid, 3, 3, [], 2);

    expect($withDefault)->toBe($withExplicit);
});

it('preserves existing behavior when no styles are passed', function () {
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        ['#', 0, 0],
    ];

    $withStyles = $this->numberer->number($grid, 3, 3, []);
    $withoutStyles = $this->numberer->number($grid, 3, 3);

    expect($withStyles)->toBe($withoutStyles);
});
