<?php

use App\Enums\PdfLayout;
use App\Services\PdfWrapLayout;

/**
 * Plan a 15×15 puzzle on a portrait letter page, measuring text as half its font size per character.
 *
 * @param  array<string, mixed>  $header
 * @param  array{pattern: string, prompt?: ?string}|null  $metaAnswer
 * @return array<string, mixed>
 */
function planWrapLayout(PdfLayout $layout, int $cluesPerDirection = 30, string $clueText = 'Short clue', array $header = [], ?array $metaAnswer = null): array
{
    $clues = fn (): array => array_map(
        fn (int $number): array => ['number' => $number, 'clue' => $clueText],
        range(1, $cluesPerDirection),
    );

    $measure = fn (string $text, string $fontStyle, float $fontSize): float => mb_strlen($text) * $fontSize * 0.5;

    return (new PdfWrapLayout($measure))->plan($layout, 504.0, 648.0, 15, 15, ['title' => 'Wrapped', ...$header], $clues(), $clues(), $metaAnswer);
}

/**
 * @param  array<string, mixed>  $plan
 * @return list<string>
 */
function plannedClueNumbers(array $plan): array
{
    return collect($plan['pages'])
        ->flatMap(fn (array $page): array => $page['regions'])
        ->flatMap(fn (array $region): array => $region['items'])
        ->where('type', 'clue')
        ->pluck('number')
        ->all();
}

it('puts the grid in the chosen corner and starts the clues in the left column', function (PdfLayout $layout, bool $gridOnRight, bool $gridAtBottom, bool $cluesStartBelowGrid, bool $cluesEndBelowGrid) {
    $plan = planWrapLayout($layout, cluesPerDirection: 40);

    expect($plan['pages'])->toHaveCount(1);

    $bodyTop = array_sum(array_column($plan['header'], 'height'));
    $grid = $plan['pages'][0]['grid'];
    $regions = $plan['pages'][0]['regions'];
    $gridBottom = $grid['top'] + 15 * $plan['cellSize'];

    expect($regions)->toHaveCount(3)
        ->and($gridOnRight ? $grid['left'] + $plan['gridWidth'] : $grid['left'])->toEqualWithDelta($gridOnRight ? 504.0 : 0.0, 0.01)
        ->and($grid['top'] > $bodyTop)->toBe($gridAtBottom)
        ->and($regions[0]['left'])->toBe(0.0)
        ->and($regions[0]['top'] > $gridBottom)->toBe($cluesStartBelowGrid)
        ->and($regions[2]['top'] > $gridBottom)->toBe($cluesEndBelowGrid)
        ->and($regions[2]['left'])->toBeGreaterThan($regions[1]['left'])
        ->and($regions[$gridOnRight ? 0 : 2]['top'])->toEqualWithDelta($bodyTop, 0.01);
})->with([
    'upper left' => [PdfLayout::GridTopLeft, false, false, true, false],
    'upper right' => [PdfLayout::GridTopRight, true, false, false, true],
    'lower left' => [PdfLayout::GridBottomLeft, false, true, false, false],
    'lower right' => [PdfLayout::GridBottomRight, true, true, false, false],
]);

it('keeps every clue once and in order, across first then down', function () {
    $plan = planWrapLayout(PdfLayout::GridTopLeft, cluesPerDirection: 30);

    $numbers = array_map(fn (int $number): string => $number.'.', range(1, 30));

    expect(plannedClueNumbers($plan))->toBe([...$numbers, ...$numbers]);
});

it('shrinks the clues or the grid rather than spilling onto a second page', function () {
    $roomy = planWrapLayout(PdfLayout::GridTopRight, cluesPerDirection: 10);
    $crowded = planWrapLayout(PdfLayout::GridTopRight, cluesPerDirection: 60);

    expect($crowded['pages'])->toHaveCount(1)
        ->and(plannedClueNumbers($crowded))->toHaveCount(120)
        ->and($crowded['fontSize'] < $roomy['fontSize'] || $crowded['cellSize'] < $roomy['cellSize'])->toBeTrue();
});

it('continues clues that cannot share the grid page on further pages', function () {
    $plan = planWrapLayout(PdfLayout::GridBottomRight, cluesPerDirection: 150, clueText: 'A clue long enough to need more than one line in a narrow column');

    expect(count($plan['pages']))->toBeGreaterThan(1)
        ->and($plan['pages'][0]['grid'])->not->toBeNull()
        ->and($plan['pages'][1]['grid'])->toBeNull()
        ->and($plan['pages'][1]['showsHeader'])->toBeFalse()
        ->and(plannedClueNumbers($plan))->toHaveCount(300);
});

it('breaks a clue into lines that fit its column', function () {
    $plan = planWrapLayout(PdfLayout::GridTopLeft, cluesPerDirection: 5, clueText: 'A clue long enough to need more than one line in a narrow column');

    $clue = collect($plan['pages'][0]['regions'][0]['items'])->firstWhere('type', 'clue');
    $columnWidth = $plan['pages'][0]['regions'][0]['width'];

    expect(count($clue['lines']))->toBeGreaterThan(1)
        ->and(implode(' ', $clue['lines']))->toBe('A clue long enough to need more than one line in a narrow column');

    foreach ($clue['lines'] as $line) {
        expect(mb_strlen($line) * $plan['fontSize'] * 0.5)->toBeLessThanOrEqual($columnWidth);
    }
});

it('gives a header that fills most of the page a page of its own', function () {
    $plan = planWrapLayout(PdfLayout::GridTopLeft, header: ['narrative' => implode("\n", array_fill(0, 40, 'A line of narrative.'))]);

    expect($plan['pages'][0]['showsHeader'])->toBeTrue()
        ->and($plan['pages'][0]['grid'])->toBeNull()
        ->and($plan['pages'][1]['showsHeader'])->toBeFalse()
        ->and($plan['pages'][1]['grid'])->toBe(['left' => 0.0, 'top' => 0.0]);
});

it('puts the meta answer blanks under the grid and keeps the clues clear of them', function (PdfLayout $layout) {
    $plan = planWrapLayout($layout, metaAnswer: ['pattern' => '____ ____', 'prompt' => 'What is the hidden theme?']);
    $page = $plan['pages'][0];
    $meta = $page['metaAnswer'];
    $gridBottom = $page['grid']['top'] + $plan['cellSize'] * 15;

    expect($plan['pages'])->toHaveCount(1)
        ->and($meta['promptLines'])->toBe(['What is the hidden theme?'])
        ->and($meta['blankLines'])->toHaveCount(1)
        ->and($meta['top'])->toBeGreaterThan($gridBottom)
        ->and($meta['top'] + 40.0)->toBeLessThanOrEqual(648.0)
        ->and($meta['left'])->toBeGreaterThanOrEqual(0.0)
        ->and($meta['left'] + $meta['width'])->toBeLessThanOrEqual(504.0);

    // Clue columns that share the grid's side of the page start below the blanks or end above the grid.
    foreach ($page['regions'] as $region) {
        $overlapsHorizontally = $region['left'] < $meta['left'] + $meta['width'] && $meta['left'] < $region['left'] + $region['width'];
        if ($overlapsHorizontally) {
            expect($region['top'] < $page['grid']['top'] || $region['top'] > $meta['top'] + 40.0)->toBeTrue();
        }
    }
})->with([
    PdfLayout::GridTopLeft,
    PdfLayout::GridBottomRight,
]);

it('plans no meta answer for a puzzle without one', function () {
    expect(planWrapLayout(PdfLayout::GridTopLeft)['pages'][0]['metaAnswer'])->toBeNull();
});
