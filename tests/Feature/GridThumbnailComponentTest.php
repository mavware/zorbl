<?php

use App\Models\Crossword;

it('renders a grid thumbnail with block and open cells', function () {
    $grid = [
        ['#', 0, 0],
        [0, 0, '#'],
        [0, 0, 0],
    ];

    $html = $this->blade(
        '<x-grid-thumbnail :grid="$grid" :width="$width" :height="$height" />',
        ['grid' => $grid, 'width' => 3, 'height' => 3]
    );

    $html->assertSee('grid-template-columns: repeat(3', false)
        ->assertSee('width: 24px', false)
        ->assertSee('bg-zinc-800 dark:bg-zinc-300', false)
        ->assertSee('bg-elevated', false);
});

it('renders null cells as invisible', function () {
    $grid = [
        [null, 0],
        [0, 0],
    ];

    $html = $this->blade(
        '<x-grid-thumbnail :grid="$grid" :width="$width" :height="$height" />',
        ['grid' => $grid, 'width' => 2, 'height' => 2]
    );

    $html->assertSee('invisible', false);
});

it('respects custom cell size and max width', function () {
    $grid = Crossword::emptyGrid(5, 5);

    $html = $this->blade(
        '<x-grid-thumbnail :grid="$grid" :width="5" :height="5" :cell-size="10" :max-width="40" />',
        ['grid' => $grid]
    );

    $html->assertSee('width: 40px', false);
});

it('uses default cell size of 8 and max width of 120', function () {
    $grid = Crossword::emptyGrid(10, 10);

    $html = $this->blade(
        '<x-grid-thumbnail :grid="$grid" :width="10" :height="10" />',
        ['grid' => $grid]
    );

    $html->assertSee('width: 80px', false);
});

it('caps width at max width when grid is large', function () {
    $grid = Crossword::emptyGrid(20, 20);

    $html = $this->blade(
        '<x-grid-thumbnail :grid="$grid" :width="20" :height="20" />',
        ['grid' => $grid]
    );

    $html->assertSee('width: 120px', false);
});

it('accepts additional attributes', function () {
    $grid = Crossword::emptyGrid(3, 3);

    $html = $this->blade(
        '<x-grid-thumbnail class="shrink-0" :grid="$grid" :width="3" :height="3" />',
        ['grid' => $grid]
    );

    $html->assertSee('shrink-0', false);
});
