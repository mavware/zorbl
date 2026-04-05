<?php

use App\Models\Word;
use App\Services\GridFiller;

beforeEach(function () {
    // Seed a small test word list for a simple 3x3 grid
    Word::insert([
        ['word' => 'CAT', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'COT', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CUT', 'length' => 3, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ACE', 'length' => 3, 'score' => 65.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'ATE', 'length' => 3, 'score' => 58.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TOE', 'length' => 3, 'score' => 52.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TAP', 'length' => 3, 'score' => 48.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'APE', 'length' => 3, 'score' => 45.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'OAT', 'length' => 3, 'score' => 44.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'OPE', 'length' => 3, 'score' => 30.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TEA', 'length' => 3, 'score' => 62.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'PEA', 'length' => 3, 'score' => 42.0, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

it('fills a simple grid with valid words', function () {
    $filler = app(GridFiller::class);

    // 3x3 open grid — all cells are white
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    $solution = [
        ['', '', ''],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->not->toBeEmpty();

    // All filled words should be 3 letters
    foreach ($result['fills'] as $fill) {
        expect(strlen($fill['word']))->toBe(3)
            ->and($fill['direction'])->toBeIn(['across', 'down']);
    }
});

it('respects pre-filled letters', function () {
    $filler = app(GridFiller::class);

    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    // Pre-fill first row with CAT
    $solution = [
        ['C', 'A', 'T'],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    // Fills should not include 1 across since it's already filled
    $acrossFills = collect($result['fills'])->where('direction', 'across')->where('number', 1);
    expect($acrossFills)->toBeEmpty();

    // Down words should start with C, A, T respectively
    foreach ($result['fills'] as $fill) {
        if ($fill['direction'] === 'down') {
            if ($fill['number'] === 1) {
                expect($fill['word'][0])->toBe('C');
            } elseif ($fill['number'] === 2) {
                expect($fill['word'][0])->toBe('A');
            } elseif ($fill['number'] === 3) {
                expect($fill['word'][0])->toBe('T');
            }
        }
    }
});

it('returns success false for impossible grid', function () {
    $filler = app(GridFiller::class);

    // Grid where pre-filled letters make it impossible
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    // ZZZ is not a word and forces impossible crossing constraints
    $solution = [
        ['Z', 'Z', 'Z'],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeFalse();
});

it('returns already filled message when grid is complete', function () {
    $filler = app(GridFiller::class);

    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    $solution = [
        ['C', 'A', 'T'],
        ['O', 'T', 'E'],
        ['T', 'E', 'A'],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue()
        ->and($result['fills'])->toBeEmpty()
        ->and($result['message'])->toContain('already fully filled');
});

it('does not use the same word twice', function () {
    $filler = app(GridFiller::class);

    // 3x3 open grid
    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    $solution = [
        ['', '', ''],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue();

    $words = array_map(fn ($fill) => $fill['word'], $result['fills']);
    expect($words)->toHaveCount(count(array_unique($words)));
});

it('does not repeat a pre-filled word', function () {
    $filler = app(GridFiller::class);

    $grid = [
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ];

    // Pre-fill first row with CAT
    $solution = [
        ['C', 'A', 'T'],
        ['', '', ''],
        ['', '', ''],
    ];

    $result = $filler->fill($grid, $solution, 3, 3, [], 3);

    expect($result['success'])->toBeTrue();

    // None of the filled words should be CAT since it's already in the grid
    $filledWords = array_map(fn ($fill) => $fill['word'], $result['fills']);
    expect($filledWords)->not->toContain('CAT');
});

it('handles grid with blocks', function () {
    $filler = app(GridFiller::class);

    // 3x3 grid with a block in center
    $grid = [
        [1, 0, '#'],
        [0, '#', 2],
        ['#', 3, 0],
    ];

    $solution = [
        ['', '', '#'],
        ['', '#', ''],
        ['#', '', ''],
    ];

    // With a block pattern, slots may be too short (length < 3)
    // This verifies it doesn't crash
    $result = $filler->fill($grid, $solution, 3, 3, [], 2);

    expect($result)->toHaveKeys(['success', 'fills', 'message']);
});
