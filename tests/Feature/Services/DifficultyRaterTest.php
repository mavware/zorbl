<?php

use App\Models\Crossword;
use App\Services\DifficultyRater;

test('small grid without blocks rates as easy', function () {
    $crossword = Crossword::factory()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    $rater = new DifficultyRater;
    $result = $rater->rate($crossword);

    expect($result['label'])->toBe('Easy')
        ->and($result['score'])->toBeGreaterThanOrEqual(1.0)
        ->and($result['score'])->toBeLessThan(2.0);
});

test('large grid with blocks rates higher', function () {
    $crossword = Crossword::factory()->withBlocks()->create([
        'width' => 15,
        'height' => 15,
    ]);

    $rater = new DifficultyRater;
    $result = $rater->rate($crossword);

    expect($result['score'])->toBeGreaterThanOrEqual(1.0)
        ->and($result['score'])->toBeLessThanOrEqual(5.0);
});

test('solve time factor increases difficulty', function () {
    $crossword = Crossword::factory()->create([
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
    ]);

    $rater = new DifficultyRater;

    $withoutTime = $rater->rate($crossword);
    $withLongTime = $rater->rate($crossword, 3000.0); // 50 min

    expect($withLongTime['score'])->toBeGreaterThan($withoutTime['score']);
});

test('score to label mapping is correct', function () {
    $rater = new DifficultyRater;

    expect($rater->scoreToLabel(1.0))->toBe('Easy')
        ->and($rater->scoreToLabel(1.9))->toBe('Easy')
        ->and($rater->scoreToLabel(2.0))->toBe('Medium')
        ->and($rater->scoreToLabel(2.9))->toBe('Medium')
        ->and($rater->scoreToLabel(3.0))->toBe('Hard')
        ->and($rater->scoreToLabel(3.9))->toBe('Hard')
        ->and($rater->scoreToLabel(4.0))->toBe('Expert')
        ->and($rater->scoreToLabel(5.0))->toBe('Expert');
});

test('rating includes factor breakdown', function () {
    $crossword = Crossword::factory()->create([
        'width' => 10,
        'height' => 10,
        'grid' => Crossword::emptyGrid(10, 10),
    ]);

    $rater = new DifficultyRater;
    $result = $rater->rate($crossword);

    expect($result['factors'])->toHaveKeys(['size', 'density', 'word_length']);
});

test('rating with solve time includes time factor', function () {
    $crossword = Crossword::factory()->create([
        'width' => 10,
        'height' => 10,
        'grid' => Crossword::emptyGrid(10, 10),
    ]);

    $rater = new DifficultyRater;
    $result = $rater->rate($crossword, 600.0);

    expect($result['factors'])->toHaveKey('solve_time');
});
