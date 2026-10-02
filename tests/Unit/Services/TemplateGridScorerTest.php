<?php

use App\Enums\TemplateGeneratorStyle;
use App\Services\TemplateGridScorer;
use App\Support\TemplateScoringWeights;
use App\Support\TemplateSearchOptions;
use App\Support\TemplateTargets;

/**
 * @param  list<string>  $rows  One string per row, '#' for a block and '.' for a white cell
 * @return array<int, array<int, int|string>>
 */
function scorerGrid(array $rows): array
{
    return array_map(
        fn (string $row): array => array_map(fn (string $cell): int|string => $cell === '#' ? '#' : 0, str_split($row)),
        $rows,
    );
}

beforeEach(function () {
    $this->scorer = new TemplateGridScorer;
});

it('accepts an open grid and measures it', function () {
    $score = $this->scorer->score(
        scorerGrid(['.....', '.....', '.....', '.....', '.....']),
        TemplateTargets::for(5, TemplateGeneratorStyle::Standard),
    );

    expect($score->isValid())->toBeTrue()
        ->and($score->blockCount)->toBe(0)
        ->and($score->wordCount)->toBe(10)
        ->and($score->averageLength)->toEqualWithDelta(5.0, 0.0001)
        ->and($score->largestOpenSquare)->toBe(5)
        ->and($score->fullWidthEntryCount)->toBe(10)
        ->and($score->chokepointCount)->toBe(0);
});

it('flags squares left in only one entry', function () {
    $score = $this->scorer->score(
        scorerGrid(['.#...', '.....', '.....', '.....', '...#.']),
        TemplateTargets::for(5, TemplateGeneratorStyle::Standard)->with(['minTouchingBlocks' => 0]),
    );

    expect($score->isValid())->toBeFalse()
        ->and($score->hardViolations)->toContain('2 unchecked square(s) in only one entry');
});

it('penalises entries outside the wanted lengths without rejecting the grid', function () {
    $grid = scorerGrid(['..#....', '.......', '.......', '.......', '.......', '.......', '....#..']);
    $targets = TemplateTargets::for(7, TemplateGeneratorStyle::Standard)->with(['minTouchingBlocks' => 0]);

    $score = $this->scorer->score($grid, $targets);
    $ignored = $this->scorer->score($grid, $targets->with(['weights' => new TemplateScoringWeights(shortEntry: 0.0)]));
    $wanted = $this->scorer->score($grid, $targets->with(['minEntryLength' => 2]));

    expect($score->isValid())->toBeTrue()
        ->and($score->shortEntryCount)->toBe(2)
        ->and($score->wordCount)->toBe(16)
        ->and($score->energy - $ignored->energy)->toEqualWithDelta(2 * 1000.0, 0.0001)
        ->and($wanted->shortEntryCount)->toBe(0)
        ->and($wanted->energy)->toBeLessThan($score->energy);

    $long = $this->scorer->score(
        scorerGrid(['.......', '.......', '.......', '.......', '.......', '.......', '.......']),
        $targets->with(['maxEntryLength' => 5]),
    );

    expect($long->isValid())->toBeTrue()
        ->and($long->longEntryCount)->toBe(14);
});

it('flags white squares that are not all connected', function () {
    $score = $this->scorer->score(
        scorerGrid(['.......', '.......', '.......', '#######', '.......', '.......', '.......']),
        TemplateTargets::for(7, TemplateGeneratorStyle::Standard),
    );

    expect($score->hardViolations)->toContain('white squares are split into 2 separate areas');
});

it('flags a 2x2 clump of blocks', function () {
    $score = $this->scorer->score(
        scorerGrid(['##.....', '##.....', '.......', '.......', '.......', '.....##', '.....##']),
        TemplateTargets::for(7, TemplateGeneratorStyle::Standard),
    );

    expect($score->hardViolations)->toContain('2 2×2 clump(s) of blocks');
});

it('flags a run of blocks longer than the limit', function () {
    $score = $this->scorer->score(
        scorerGrid([
            '...........', '...........', '...........', '...........', '...........',
            '#####.#####',
            '...........', '...........', '...........', '...........', '...........',
        ]),
        TemplateTargets::for(11, TemplateGeneratorStyle::Standard),
    );

    expect($score->hardViolations)->toContain('a run of more than 4 blocks in a row');
});

it('counts corner blocks as cheater squares', function () {
    $score = $this->scorer->score(
        scorerGrid(['#...#', '.....', '.....', '.....', '#...#']),
        TemplateTargets::for(5, TemplateGeneratorStyle::Standard)->with(['minTouchingBlocks' => 0]),
    );

    expect($score->isValid())->toBeTrue()
        ->and($score->cheaterCount)->toBe(4)
        ->and($score->borderShare)->toEqualWithDelta(1.0, 0.0001);
});

it('does not count a block that separates two entries as a cheater', function () {
    $score = $this->scorer->score(
        scorerGrid(['.......', '.......', '.......', '...#...', '.......', '.......', '.......']),
        TemplateTargets::for(7, TemplateGeneratorStyle::Standard)->with(['minTouchingBlocks' => 0]),
    );

    expect($score->cheaterCount)->toBe(0);
});

it('counts chokepoints where one cell links two sections', function () {
    $score = $this->scorer->score(
        scorerGrid(['..#..', '..#..', '.....', '..#..', '..#..']),
        TemplateTargets::for(5, TemplateGeneratorStyle::Standard),
    );

    // The centre cell and its two row neighbours each split the grid if removed.
    expect($score->chokepointCount)->toBe(3);
});

it('scores a curated 15x15 template with no hard violations', function () {
    // The curated grid has a lone block at (8, 5), so the touching rule is switched off here.
    $score = $this->scorer->score(
        scorerGrid([
            '.....#....#....',
            '.....#....#....',
            '.....#....#....',
            '..........#....',
            '###.....#......',
            '......##....###',
            '...##....#.....',
            '...............',
            '.....#....##...',
            '###....##......',
            '......#.....###',
            '....#..........',
            '....#....#.....',
            '....#....#.....',
            '....#....#.....',
        ]),
        TemplateTargets::for(15, TemplateGeneratorStyle::Standard)->with(['minTouchingBlocks' => 0]),
    );

    expect($score->isValid())->toBeTrue()
        ->and($score->blockCount)->toBe(38)
        ->and($score->wordCount)->toBe(76)
        ->and($score->largestOpenSquare)->toBeLessThanOrEqual(5);
});

it('scales the word limit through the published 15x15 and 21x21 limits', function () {
    expect(TemplateTargets::maxWordCount(15))->toBe(78)
        ->and(TemplateTargets::maxWordCount(21))->toBe(140)
        ->and(TemplateTargets::maxWordCount(23))->toBe(168)
        ->and(TemplateTargets::maxWordCount(13))->toBe(62)
        ->and(TemplateTargets::maxWordCount(17))->toBe(96);
});

it('rejects sizes and styles it cannot generate', function () {
    expect(fn () => TemplateTargets::for(4, TemplateGeneratorStyle::Standard))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TemplateTargets::for(36, TemplateGeneratorStyle::Standard))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TemplateTargets::for(9, TemplateGeneratorStyle::Themeless))->toThrow(InvalidArgumentException::class);
});

it('flags blocks that touch too few other blocks, which is the default', function () {
    $grid = scorerGrid(['.......', '.......', '.......', '...#...', '.......', '.......', '.......']);
    $targets = TemplateTargets::for(7, TemplateGeneratorStyle::Standard);
    $relaxed = $targets->with(['minTouchingBlocks' => 0]);

    expect($targets->minTouchingBlocks)->toBe(1)
        ->and($this->scorer->score($grid, $relaxed)->isValid())->toBeTrue()
        ->and($this->scorer->score($grid, $relaxed)->lonelyBlockCount)->toBe(0);

    $score = $this->scorer->score($grid, $targets);

    expect($score->isValid())->toBeFalse()
        ->and($score->lonelyBlockCount)->toBe(1)
        ->and($score->hardViolations)->toContain('1 block(s) touching fewer than 1 other block(s)');
});

it('counts diagonal neighbours as touching but not the grid edge', function () {
    $targets = TemplateTargets::for(7, TemplateGeneratorStyle::Standard);

    // Two diagonal pairs: each block has exactly one neighbour, on the diagonal.
    $diagonal = $this->scorer->score(
        scorerGrid(['.......', '.......', '.......', '...#...', '..#.#..', '...#...', '.......']),
        TemplateTargets::for(7, TemplateGeneratorStyle::Standard)->with(['minTouchingBlocks' => 2]),
    );

    // Corner blocks sit against the edge but touch no other block.
    $corners = $this->scorer->score(scorerGrid(['#.....#', '.......', '.......', '.......', '.......', '.......', '#.....#']), $targets);

    expect($diagonal->lonelyBlockCount)->toBe(0)
        ->and($corners->lonelyBlockCount)->toBe(4);
});

it('applies overrides to the default targets', function () {
    $targets = TemplateTargets::for(15, TemplateGeneratorStyle::Standard)->with([
        'minBlocks' => 40,
        'maxBlocks' => 44,
        'maxCheaters' => 2,
        'minTouchingBlocks' => 1,
        'lengthShares' => [3 => 0.5],
        'weights' => new TemplateScoringWeights(cheater: 0.0),
    ]);

    expect($targets->minBlocks)->toBe(40)
        ->and($targets->maxBlocks)->toBe(44)
        ->and($targets->maxCheaters)->toBe(2)
        ->and($targets->minTouchingBlocks)->toBe(1)
        ->and($targets->weights->cheater)->toBe(0.0)
        ->and($targets->weights->chokepoint)->toBe(10.0)
        ->and($targets->maxWords)->toBe(78)
        ->and(array_sum($targets->lengthShares))->toEqualWithDelta(1.0, 0.0001)
        // 3-letter share was raised to 0.5 against 0.816 for the rest, then rescaled.
        ->and($targets->lengthShares[3])->toEqualWithDelta(0.5 / 1.316, 0.0001);
});

it('rejects overrides that contradict each other or name an unknown setting', function (array $overrides) {
    TemplateTargets::for(15, TemplateGeneratorStyle::Standard)->with($overrides);
})->with([
    'minimum blocks above maximum' => [['minBlocks' => 50, 'maxBlocks' => 40]],
    'minimum words above maximum' => [['minWords' => 90]],
    'entries shorter than two' => [['minEntryLength' => 1]],
    'entry longer than the grid' => [['maxEntryLength' => 16]],
    'too many touching blocks' => [['minTouchingBlocks' => 3]],
    'unknown setting' => [['blockiness' => 3]],
    'changing the size' => [['size' => 21]],
])->throws(InvalidArgumentException::class);

it('rejects search options that cannot work', function (array $options) {
    new TemplateSearchOptions(...$options);
})->with([
    'no iterations' => [['iterationsPerCell' => 0]],
    'end above start temperature' => [['startTemperature' => 1.0, 'endTemperature' => 2.0]],
    'zero end temperature' => [['endTemperature' => 0.0]],
    'no move types' => [['flipWeight' => 0.0, 'paintWeight' => 0.0, 'eraseWeight' => 0.0, 'slideWeight' => 0.0]],
    'duplicate threshold above 100%' => [['maxSharedBlocks' => 1.5]],
])->throws(InvalidArgumentException::class);

it('rejects negative scoring weights', function () {
    new TemplateScoringWeights(chokepoint: -1.0);
})->throws(InvalidArgumentException::class);
