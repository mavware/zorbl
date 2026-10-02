<?php

use App\Enums\TemplateGeneratorStyle;
use App\Models\Template;
use App\Services\ProceduralTemplateGenerator;
use App\Services\TemplateGridScorer;
use App\Support\TemplateScoringWeights;
use App\Support\TemplateSearchOptions;
use App\Support\TemplateTargets;

test('generated templates follow the construction rules and size targets', function (int $size) {
    $candidates = app(ProceduralTemplateGenerator::class)->generate($size, TemplateGeneratorStyle::Standard, count: 1, seed: 7);
    $targets = TemplateTargets::for($size, TemplateGeneratorStyle::Standard);

    expect($candidates)->toHaveCount(1);

    $candidate = $candidates[0];
    $score = app(TemplateGridScorer::class)->score($candidate->grid, $targets);

    expect($candidate->isValid())->toBeTrue()
        ->and($candidate->width)->toBe($size)
        ->and($candidate->height)->toBe($size)
        ->and($candidate->stats->isRotationallySymmetric)->toBeTrue()
        ->and($candidate->stats->isConnected)->toBeTrue()
        ->and($candidate->stats->isFullyChecked)->toBeTrue()
        ->and($candidate->stats->minWordLength)->toBeGreaterThanOrEqual(3)
        ->and($score->hardViolations)->toBe([])
        ->and($score->blockCount)->toBeBetween($targets->minBlocks, $targets->maxBlocks)
        ->and($score->wordCount)->toBeBetween($targets->minWords, $targets->maxWords)
        ->and($score->largestOpenSquare)->toBeLessThanOrEqual(4)
        ->and($score->cheaterCount)->toBe(0)
        ->and($score->lonelyBlockCount)->toBe(0);
})->with([11, 13, 17]);

test('the same seed produces the same template and a different seed a different one', function () {
    $generator = app(ProceduralTemplateGenerator::class);

    // 9x9 grids have so few good layouts that two seeds can land on the same one; 11x11 does not.
    $first = $generator->generate(11, count: 1, seed: 42);
    $again = $generator->generate(11, count: 1, seed: 42);
    $other = $generator->generate(11, count: 1, seed: 4242);

    expect($first[0]->grid)->toBe($again[0]->grid)
        ->and($first[0]->name)->toBe($again[0]->name)
        ->and($other[0]->grid)->not->toBe($first[0]->grid);
});

test('a grid that already exists as a template is never returned', function () {
    $generator = app(ProceduralTemplateGenerator::class);

    $first = $generator->generate(9, count: 1, seed: 42);

    Template::factory()->square(9)->inactive()->create(['grid' => $first[0]->grid]);

    $second = $generator->generate(9, count: 1, seed: 42);

    expect(collect($second)->pluck('grid'))->not->toContain($first[0]->grid);
});

test('themed templates reserve the requested theme slots', function () {
    $candidates = app(ProceduralTemplateGenerator::class)->generate(13, TemplateGeneratorStyle::Themed, count: 1, seed: 3, themeLengths: [9, 13]);

    expect($candidates)->toHaveCount(1);

    $grid = $candidates[0]->grid;

    // Row 4: a 9-letter slot flush left, closed by a block; its twin sits flush right on row 10.
    expect(array_slice($grid[3], 0, 10))->toBe([0, 0, 0, 0, 0, 0, 0, 0, 0, '#'])
        ->and(array_slice($grid[9], 3))->toBe(['#', 0, 0, 0, 0, 0, 0, 0, 0, 0])
        ->and($grid[6])->toBe(array_fill(0, 13, 0))
        ->and($candidates[0]->bestFor)->toBe('Fits 9-letter slot across row 4; 13-letter slot across row 7.');
});

test('theme slot lengths that cannot fit are rejected', function () {
    app(ProceduralTemplateGenerator::class)->generate(13, TemplateGeneratorStyle::Themed, count: 1, seed: 3, themeLengths: [11]);
})->throws(InvalidArgumentException::class);

test('themed and themeless styles are rejected below 11x11', function (TemplateGeneratorStyle $style) {
    app(ProceduralTemplateGenerator::class)->generate(9, $style, count: 1, seed: 1);
})->with([TemplateGeneratorStyle::Themed, TemplateGeneratorStyle::Themeless])->throws(InvalidArgumentException::class);

test('by default no block stands alone', function () {
    $candidates = app(ProceduralTemplateGenerator::class)->generate(13, count: 1, seed: 77);

    expect($candidates)->toHaveCount(1);

    $grid = $candidates[0]->grid;
    $blocks = 0;

    foreach ($grid as $r => $row) {
        foreach ($row as $c => $cell) {
            if ($cell !== '#') {
                continue;
            }

            $blocks++;
            $touching = 0;

            foreach ([[-1, -1], [-1, 0], [-1, 1], [0, -1], [0, 1], [1, -1], [1, 0], [1, 1]] as [$dr, $dc]) {
                $touching += ($grid[$r + $dr][$c + $dc] ?? null) === '#' ? 1 : 0;
            }

            expect($touching)->toBeGreaterThanOrEqual(1, "Block at ($r, $c) stands alone");
        }
    }

    expect($blocks)->toBeGreaterThan(0)
        ->and($candidates[0]->philosophy)->not->toContain('Custom settings')
        ->and($candidates[0]->strengths)->toContain('Every block touches at least 1 other block(s)');
});

test('target overrides steer the generated grid', function () {
    $candidates = app(ProceduralTemplateGenerator::class)->generate(11, count: 1, seed: 7, targetOverrides: [
        'minBlocks' => 22,
        'maxBlocks' => 24,
        'weights' => new TemplateScoringWeights(blockBand: 40.0),
    ]);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]->stats->blockCount)->toBeBetween(22, 24)
        ->and($candidates[0]->philosophy)->toContain('minBlocks=22')
        ->and($candidates[0]->philosophy)->toContain('blockBand=40');
});

test('search options control the number of attempts and are recorded on the draft', function () {
    $generator = app(ProceduralTemplateGenerator::class);
    $search = new TemplateSearchOptions(iterationsPerCell: 60, attemptsPerTemplate: 1);

    $candidates = $generator->generate(9, count: 2, seed: 42, search: $search);

    // One attempt per template: seeds 42 and 43 only.
    expect($candidates)->not->toBeEmpty();

    foreach ($candidates as $candidate) {
        expect($candidate->name)->toBeIn(['Standard 9 #42', 'Standard 9 #43'])
            ->and($candidate->philosophy)->toContain('iterationsPerCell=60')
            ->and($candidate->philosophy)->toContain('attemptsPerTemplate=1');
    }
});

test('an empty grid is never returned as a template', function () {
    // Zero weight on density and word count removes every reason to place a block.
    $candidates = app(ProceduralTemplateGenerator::class)->generate(9, count: 1, seed: 1, targetOverrides: [
        'weights' => new TemplateScoringWeights(blockBand: 0.0, wordBand: 0.0, lengthMix: 0.0, fullWidth: 0.0),
    ]);

    foreach ($candidates as $candidate) {
        expect($candidate->stats->blockCount)->toBeGreaterThan(0);
    }

    expect(true)->toBeTrue();
});

test('seed words get symmetric slots and are listed on the draft', function () {
    $candidates = app(ProceduralTemplateGenerator::class)->generate(15, count: 1, seed: 5, seedWords: [
        ['word' => 'Hello World', 'direction' => 'across'],
        ['word' => 'LUCKY SEVEN', 'direction' => 'down'],
    ]);

    expect($candidates)->toHaveCount(1);

    $grid = $candidates[0]->grid;

    // HELLOWORLD (10) sits flush left on row 4 with a block after it; its twin is flush right on row 12.
    expect(array_slice($grid[3], 0, 11))->toBe([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '#'])
        ->and(array_slice($grid[11], 4))->toBe(['#', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])
        // LUCKYSEVEN (10) runs down column 4 from the top with a block after it.
        ->and(array_slice(array_column($grid, 3), 0, 11))->toBe([0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '#'])
        ->and($candidates[0]->bestFor)->toBe('Fits HELLOWORLD across row 4; LUCKYSEVEN down column 4.')
        ->and($candidates[0]->isValid())->toBeTrue();
});

test('seed words that cannot be placed are rejected', function () {
    app(ProceduralTemplateGenerator::class)->generate(13, count: 1, seed: 5, seedWords: [['word' => 'ELEVENLETTER']]);
})->throws(InvalidArgumentException::class, 'cannot be placed');
