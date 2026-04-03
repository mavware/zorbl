<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('completeness is 100% for a fully filled puzzle', function () {
    $crossword = Crossword::factory()->create([
        'title' => 'My Puzzle',
        'author' => 'Jane',
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'First across'],
            ['number' => 3, 'clue' => 'Second across'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'First down'],
            ['number' => 2, 'clue' => 'Second down'],
        ],
    ]);

    $result = $crossword->completeness();

    expect($result['percentage'])->toBe(100)
        ->and($result['checks'])->each->toBeTrue();
});

test('completeness detects missing title', function () {
    $crossword = Crossword::factory()->create([
        'title' => 'Untitled Puzzle',
        'author' => 'Jane',
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [['number' => 1, 'clue' => 'Clue']],
        'clues_down' => [['number' => 1, 'clue' => 'Clue']],
    ]);

    expect($crossword->completeness()['checks']['title'])->toBeFalse();
});

test('completeness detects empty title', function () {
    $crossword = Crossword::factory()->create([
        'title' => '',
        'author' => 'Jane',
    ]);

    expect($crossword->completeness()['checks']['title'])->toBeFalse();
});

test('completeness detects missing author', function () {
    $crossword = Crossword::factory()->create(['author' => null]);

    expect($crossword->completeness()['checks']['author'])->toBeFalse();
});

test('completeness detects unfilled cells', function () {
    $crossword = Crossword::factory()->create([
        'solution' => [['A', ''], ['C', 'D']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeFalse();
});

test('completeness ignores black cells for fill check', function () {
    $crossword = Crossword::factory()->create([
        'title' => 'Test',
        'author' => 'Author',
        'grid' => [[1, '#'], [2, 0]],
        'solution' => [['A', '#'], ['C', 'D']],
        'clues_across' => [['number' => 2, 'clue' => 'Clue']],
        'clues_down' => [['number' => 1, 'clue' => 'Clue']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeTrue();
});

test('completeness detects missing across clues', function () {
    $crossword = Crossword::factory()->create([
        'clues_across' => [
            ['number' => 1, 'clue' => 'Has clue'],
            ['number' => 3, 'clue' => ''],
        ],
    ]);

    expect($crossword->completeness()['checks']['clues_across'])->toBeFalse();
});

test('completeness detects missing down clues', function () {
    $crossword = Crossword::factory()->create([
        'clues_down' => [
            ['number' => 1, 'clue' => ''],
        ],
    ]);

    expect($crossword->completeness()['checks']['clues_down'])->toBeFalse();
});

test('completeness percentage is calculated correctly', function () {
    $crossword = Crossword::factory()->create([
        'title' => '',
        'author' => 'Jane',
        'grid' => [[1]],
        'solution' => [['A']],
        'clues_across' => [],
        'clues_down' => [],
    ]);

    $result = $crossword->completeness();

    expect($result['checks']['title'])->toBeFalse()
        ->and($result['checks']['author'])->toBeTrue()
        ->and($result['checks']['fill'])->toBeTrue()
        ->and($result['checks']['clues_across'])->toBeFalse()
        ->and($result['checks']['clues_down'])->toBeFalse()
        ->and($result['percentage'])->toBe(40);
});

test('publish button shows warning when puzzle is incomplete', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => '',
        'author' => '',
        'solution' => [['', ''], ['', '']],
        'clues_across' => [['number' => 1, 'clue' => '']],
        'clues_down' => [['number' => 1, 'clue' => '']],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', true)
        ->assertSet('isPublished', false);
});

test('publish proceeds without warning when puzzle is complete', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Complete Puzzle',
        'author' => 'Author',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Across 1'],
            ['number' => 3, 'clue' => 'Across 3'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Down 1'],
            ['number' => 2, 'clue' => 'Down 2'],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', false)
        ->assertSet('isPublished', true);
});

test('unpublish skips warning', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => '',
        'is_published' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->assertSet('showPublishWarning', false)
        ->assertSet('isPublished', false);
});

test('cancel publish dispatches highlight event', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => '',
        'clues_across' => [['number' => 1, 'clue' => '']],
        'clues_down' => [['number' => 1, 'clue' => '']],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptPublish')
        ->call('cancelPublish')
        ->assertSet('showPublishWarning', false)
        ->assertDispatched('highlight-incomplete');
});
