<?php

use App\Models\Word;
use App\Services\WordSuggester;

beforeEach(function () {
    // Seed a small test word list
    Word::insert([
        ['word' => 'CRANE', 'length' => 5, 'score' => 65.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CRANK', 'length' => 5, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CRACK', 'length' => 5, 'score' => 45.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CRASH', 'length' => 5, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CLANK', 'length' => 5, 'score' => 40.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'TASTE', 'length' => 5, 'score' => 70.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CAT', 'length' => 3, 'score' => 60.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'COT', 'length' => 3, 'score' => 55.0, 'created_at' => now(), 'updated_at' => now()],
        ['word' => 'CUT', 'length' => 3, 'score' => 50.0, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

it('suggests words matching a pattern', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('CR___', 5);

    expect($results)->toHaveCount(4)
        ->and(collect($results)->pluck('word')->all())->toContain('CRANE', 'CRANK', 'CRACK', 'CRASH');
});

it('returns results ordered by score descending', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('CR___', 5);

    $scores = collect($results)->pluck('score')->all();

    expect($scores)->toBe(collect($scores)->sortDesc()->values()->all());
});

it('matches specific letter positions', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('C_A__', 5);

    $words = collect($results)->pluck('word')->all();

    expect($words)->toContain('CRANE', 'CRANK', 'CRACK', 'CRASH', 'CLANK');
});

it('returns empty for fully filled pattern', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('CRANE', 5);

    expect($results)->toBeEmpty();
});

it('returns empty when no words match', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('ZZ___', 5);

    expect($results)->toBeEmpty();
});

it('respects the limit parameter', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('CR___', 5, limit: 2);

    expect($results)->toHaveCount(2);
});

it('filters by word length', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('C_T', 3);

    $words = collect($results)->pluck('word')->all();

    expect($words)->toContain('CAT', 'COT', 'CUT')
        ->and($words)->not->toContain('CRANE');
});

it('handles all-underscore pattern', function () {
    $suggester = app(WordSuggester::class);
    $results = $suggester->suggest('___', 3);

    expect($results)->toHaveCount(3);
});
