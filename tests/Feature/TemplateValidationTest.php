<?php

use App\Services\GridTemplateProvider;
use Database\Factories\TemplateFactory;

test('open grid passes rotational symmetry check', function () {
    $grid = TemplateFactory::openGrid(15, 15);

    expect(GridTemplateProvider::hasRotationalSymmetry($grid, 15, 15))->toBeTrue();
});

test('grid with a single corner block fails rotational symmetry', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][0] = '#';

    expect(GridTemplateProvider::hasRotationalSymmetry($grid, 5, 5))->toBeFalse();
});

test('grid with mirrored corner blocks passes rotational symmetry', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][0] = '#';
    $grid[4][4] = '#';

    expect(GridTemplateProvider::hasRotationalSymmetry($grid, 5, 5))->toBeTrue();
});

test('open grid passes min word length check', function () {
    $grid = TemplateFactory::openGrid(15, 15);

    expect(GridTemplateProvider::validateMinWordLength($grid, 15, 15))->toBeTrue();
});

test('grid producing a 1-letter word fails min word length check', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][1] = '#';
    $grid[4][3] = '#';

    expect(GridTemplateProvider::validateMinWordLength($grid, 5, 5))->toBeFalse();
});

test('grid producing a 2-letter word fails min word length check at default minimum', function () {
    $grid = TemplateFactory::openGrid(5, 5);
    $grid[0][2] = '#';
    $grid[4][2] = '#';

    expect(GridTemplateProvider::validateMinWordLength($grid, 5, 5))->toBeFalse();
});
