<?php

use App\Models\Template;
use App\Services\TemplateStatsService;
use App\Support\TemplateStats;
use Zorbl\CrosswordIO\GridNumberer;

beforeEach(function () {
    $this->service = new TemplateStatsService(new GridNumberer);
});

it('counts blocks and computes density', function () {
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        ['#', 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->blockCount)->toBe(2)
        ->and($stats->cellCount)->toBe(9)
        ->and($stats->whiteCount)->toBe(7)
        ->and($stats->blockDensity)->toEqualWithDelta(2 / 9, 0.0001);
});

it('counts across and down words on an open grid', function () {
    $grid = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->acrossWordCount)->toBe(3)
        ->and($stats->downWordCount)->toBe(3)
        ->and($stats->wordCount)->toBe(6);
});

it('computes word length statistics', function () {
    $grid = [
        [0, 0, 0, 0],
        [0, 0, 0, 0],
        [0, 0, 0, 0],
        [0, 0, 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 4, 4);

    expect($stats->minWordLength)->toBe(4)
        ->and($stats->maxWordLength)->toBe(4)
        ->and($stats->avgWordLength)->toBe(4.0);
});

it('detects 180-degree rotational symmetry', function () {
    $grid = [
        [0, 0, '#'],
        [0, 0, 0],
        ['#', 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isRotationallySymmetric)->toBeTrue();
});

it('detects asymmetric grids', function () {
    $grid = [
        [0, '#', 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isRotationallySymmetric)->toBeFalse();
});

it('detects horizontal mirror symmetry', function () {
    $grid = [
        [0, '#', '#', 0],
        [0, 0, 0, 0],
        [0, 0, 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 4, 3);

    expect($stats->isMirrorHorizontal)->toBeTrue()
        ->and($stats->isMirrorVertical)->toBeFalse();
});

it('detects vertical mirror symmetry', function () {
    $grid = [
        ['#', 0, '#'],
        [0, 0, 0],
        ['#', 0, '#'],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isMirrorVertical)->toBeTrue()
        ->and($stats->isMirrorHorizontal)->toBeTrue();
});

it('detects connectivity in a normal grid', function () {
    $grid = [
        [0, 0, 0],
        [0, '#', 0],
        [0, 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isConnected)->toBeTrue();
});

it('detects disconnected grids', function () {
    $grid = [
        [0, '#', 0],
        ['#', '#', '#'],
        [0, '#', 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isConnected)->toBeFalse();
});

it('detects a fully-checked grid', function () {
    $grid = [
        [0, 0, 0],
        [0, 0, 0],
        [0, 0, 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isFullyChecked)->toBeTrue();
});

it('detects a not-fully-checked grid (length-1 across runs)', function () {
    // Row 0: cells (0,0) and (0,2) sit in length-1 across runs, so they
    // belong to a down word but not to any across word of length >= 2.
    $grid = [
        [0, '#', 0],
        [0, 0, 0],
        [0, '#', 0],
    ];

    $stats = $this->service->forGrid($grid, 3, 3);

    expect($stats->isFullyChecked)->toBeFalse();
});

it('handles bars correctly (Internal Bars template)', function () {
    $grid = [
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ];
    $styles = [
        '1,1' => ['bars' => ['top', 'left']],
        '1,3' => ['bars' => ['top', 'right']],
        '3,1' => ['bars' => ['bottom', 'left']],
        '3,3' => ['bars' => ['bottom', 'right']],
    ];

    $stats = $this->service->forGrid($grid, 5, 5, $styles);

    // Per-row across runs (length >= 2):
    //   row 0: 5
    //   row 1: only the 1..3 inner run has length 3 (col 0 and col 4 are length-1)
    //   row 2: 5
    //   row 3: only the 1..3 inner run has length 3
    //   row 4: 5
    // By symmetry, downs mirror that pattern column-wise.
    expect($stats->blockCount)->toBe(0)
        ->and($stats->acrossWordCount)->toBe(5)
        ->and($stats->downWordCount)->toBe(5)
        ->and($stats->minWordLength)->toBe(3)
        ->and($stats->maxWordLength)->toBe(5)
        ->and($stats->isFullyChecked)->toBeFalse();
});

it('integrates with Template models via forTemplate', function () {
    $template = Template::factory()->make([
        'width' => 3,
        'height' => 3,
        'grid' => [
            [0, 0, 0],
            [0, 0, 0],
            [0, 0, 0],
        ],
        'styles' => null,
    ]);

    $stats = $this->service->forTemplate($template);

    expect($stats)->toBeInstanceOf(TemplateStats::class)
        ->and($stats->blockCount)->toBe(0)
        ->and($stats->wordCount)->toBe(6)
        ->and($stats->isFullyChecked)->toBeTrue();
});

it('reports zero word stats for an all-blocks grid', function () {
    $grid = [
        ['#', '#'],
        ['#', '#'],
    ];

    $stats = $this->service->forGrid($grid, 2, 2);

    expect($stats->wordCount)->toBe(0)
        ->and($stats->minWordLength)->toBe(0)
        ->and($stats->maxWordLength)->toBe(0)
        ->and($stats->avgWordLength)->toBe(0.0)
        ->and($stats->isConnected)->toBeTrue();
});
