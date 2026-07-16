<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use Livewire\Livewire;

test('analytics shows meta answer responses for puzzles with submissions', function () {
    $user = makeProUser();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES', 'Films'])
        ->create(['is_published' => true]);

    PuzzleAttempt::factory()->count(3)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'Movies',
    ]);

    PuzzleAttempt::factory()->count(2)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'WRONG GUESS',
    ]);

    PuzzleAttempt::factory()->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'Films',
    ]);

    $this->actingAs($user);

    Livewire::test('constructor-analytics')
        ->assertSee('Meta Answer Responses')
        ->assertSee('What is the hidden theme?')
        ->assertSee('Movies')
        ->assertSee('WRONG GUESS')
        ->assertSee('Films');
});

test('analytics does not show meta answer section when no puzzles have meta answers', function () {
    $user = makeProUser();
    Crossword::factory()->for($user)->create(['is_published' => true]);

    $this->actingAs($user);

    Livewire::test('constructor-analytics')
        ->assertDontSee('Meta Answer Responses');
});

test('analytics does not show meta answer section when no submissions exist', function () {
    $user = makeProUser();
    Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'])
        ->create(['is_published' => true]);

    $this->actingAs($user);

    Livewire::test('constructor-analytics')
        ->assertDontSee('Meta Answer Responses');
});

test('meta answer responses marks correct answers', function () {
    $user = makeProUser();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('Theme?', ['MOVIES'])
        ->create(['is_published' => true]);

    PuzzleAttempt::factory()->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'Movies',
    ]);

    PuzzleAttempt::factory()->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'wrong',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('constructor-analytics');
    $responses = $component->instance()->metaAnswerResponses;

    expect($responses)->toHaveCount(1);
    $puzzleResponses = $responses[0]['responses'];

    $correct = collect($puzzleResponses)->firstWhere('answer', 'Movies');
    $incorrect = collect($puzzleResponses)->firstWhere('answer', 'wrong');

    expect($correct['is_correct'])->toBeTrue()
        ->and($incorrect['is_correct'])->toBeFalse();
});

test('meta answer responses are ordered by count descending', function () {
    $user = makeProUser();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('Theme?', ['MOVIES'])
        ->create(['is_published' => true]);

    PuzzleAttempt::factory()->count(5)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'Popular Guess',
    ]);

    PuzzleAttempt::factory()->count(2)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'meta_answer' => 'Rare Guess',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('constructor-analytics');
    $responses = $component->instance()->metaAnswerResponses;

    $puzzleResponses = $responses[0]['responses'];
    expect($puzzleResponses[0]['answer'])->toBe('Popular Guess')
        ->and($puzzleResponses[0]['count'])->toBe(5)
        ->and($puzzleResponses[1]['answer'])->toBe('Rare Guess')
        ->and($puzzleResponses[1]['count'])->toBe(2);
});

test('meta answer responses excludes unpublished puzzles', function () {
    $user = makeProUser();
    Crossword::factory()
        ->for($user)
        ->withMetaAnswer('Theme?', ['MOVIES'])
        ->create(['is_published' => false]);

    $this->actingAs($user);

    Livewire::test('constructor-analytics')
        ->assertDontSee('Meta Answer Responses');
});
