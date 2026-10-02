<?php

use App\Services\TemplateGridScorer;
use App\Services\TemplateSlotPlanner;

/**
 * @return array<int, array<int, int|string>> The plan's locked cells drawn on an open grid: '#' block, 'W' locked white, 0 free
 */
function plannedGrid(array $locked, int $size): array
{
    $grid = array_fill(0, $size, array_fill(0, $size, 0));

    foreach ($locked as $index => $value) {
        $row = intdiv($index, $size + 2) - 1;
        $col = $index % ($size + 2) - 1;
        $grid[$row][$col] = $value === TemplateGridScorer::BLOCK ? '#' : 'W';
    }

    return $grid;
}

beforeEach(function () {
    $this->planner = new TemplateSlotPlanner;
});

it('gives the longest word the centre row and pairs equal lengths on one row', function () {
    $plan = $this->planner->forWords(15, [
        ['word' => 'Hello World', 'direction' => 'across'],
        ['word' => 'FIFTEENLETTERSX'],
        ['word' => 'crosswords', 'direction' => 'across'],
    ]);

    $grid = plannedGrid($plan->locked, 15);

    expect($grid[7])->toBe(array_fill(0, 15, 'W'))
        ->and(array_slice($grid[3], 0, 11))->toBe(array_merge(array_fill(0, 10, 'W'), ['#']))
        ->and(array_slice($grid[11], 4))->toBe(array_merge(['#'], array_fill(0, 10, 'W')))
        ->and($plan->fullWidthEntries)->toBe(1)
        ->and($plan->lengths)->toBe([15, 10])
        ->and($plan->placements)->toBe(['FIFTEENLETTERSX across row 8', 'HELLOWORLD and CROSSWORDS across row 4']);
});

it('chooses rows that are at least three apart when it can', function () {
    $plan = $this->planner->forWords(15, [['word' => 'ABCDEFGHIJK'], ['word' => 'ABCDEFGHI']]);
    $rows = array_map(fn (string $p): int => (int) substr($p, strrpos($p, ' ') + 1), $plan->placements);

    expect($rows)->toBe([6, 3]);
});

it('reserves down words in columns', function () {
    $plan = $this->planner->forWords(15, [['word' => 'PUZZLE MAKER', 'direction' => 'down']]);
    $grid = plannedGrid($plan->locked, 15);

    expect(array_column($grid, 3))->toBe(array_merge(array_fill(0, 11, 'W'), ['#', 0, 0, 0]))
        ->and(array_column($grid, 11))->toBe(array_merge([0, 0, 0, '#'], array_fill(0, 11, 'W')))
        ->and($plan->placements)->toBe(['PUZZLEMAKER down column 4']);
});

it('rejects words that cannot be placed', function (int $size, array $words, string $message) {
    expect(fn () => $this->planner->forWords($size, $words))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'too long for an edge slot' => [15, [['word' => 'THIRTEENLETTER']], 'cannot be placed'],
    'two letters' => [15, [['word' => 'AB']], 'cannot be placed'],
    'too many words' => [11, array_fill(0, 12, ['word' => 'ABCDE']), 'Too many across words'],
    'bad direction' => [15, [['word' => 'ABCDE', 'direction' => 'diagonal']], 'must be across or down'],
    // ABCDEFG takes the centre row with a cap at (7, 3); the down word fills column 3 through row 10.
    'across and down words colliding' => [15, [['word' => 'ABCDEFG'], ['word' => 'ABCDEFGHIJK', 'direction' => 'down']], 'overlap'],
]);

it('reserves the classic theme rows for theme lengths', function () {
    $plan = $this->planner->forThemeLengths(13, [9, 13]);
    $grid = plannedGrid($plan->locked, 13);

    expect(array_slice($grid[3], 0, 10))->toBe(array_merge(array_fill(0, 9, 'W'), ['#']))
        ->and($grid[6])->toBe(array_fill(0, 13, 'W'))
        ->and($plan->fullWidthEntries)->toBe(1)
        ->and($plan->placements)->toBe(['9-letter slot across row 4', '13-letter slot across row 7']);
});
