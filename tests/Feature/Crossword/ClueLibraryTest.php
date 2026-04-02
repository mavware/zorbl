<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('lookupClues returns clues from other published puzzles', function () {
    $creator = User::factory()->create(['name' => 'Jane']);
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Puzzle A']);

    ClueEntry::create([
        'answer' => 'CAT',
        'clue' => 'Feline pet',
        'crossword_id' => $crossword->id,
        'user_id' => $creator->id,
        'direction' => 'across',
        'clue_number' => 1,
    ]);

    $user = User::factory()->create();
    $myPuzzle = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.editor', ['crossword' => $myPuzzle]);
    $result = $component->call('lookupClues', 'CAT')->get('lookupClues');

    // If Livewire doesn't expose return values via get(), test the method directly
    if ($result === null) {
        // Call the method directly through the component instance
        $instance = $component->instance();
        $result = $instance->lookupClues('CAT');
    }

    expect($result)->toHaveCount(1)
        ->and($result[0]['clue'])->toBe('Feline pet')
        ->and($result[0]['author'])->toBe('Jane')
        ->and($result[0]['puzzle'])->toBe('Puzzle A');
});

test('lookupClues excludes clues from the current puzzle', function () {
    $user = User::factory()->create();
    $myPuzzle = Crossword::factory()->published()->for($user)->create();

    ClueEntry::create([
        'answer' => 'DOG',
        'clue' => 'My own clue',
        'crossword_id' => $myPuzzle->id,
        'user_id' => $user->id,
        'direction' => 'across',
        'clue_number' => 1,
    ]);

    $this->actingAs($user);

    $instance = Livewire::test('pages::crosswords.editor', ['crossword' => $myPuzzle])->instance();
    $result = $instance->lookupClues('DOG');

    expect($result)->toHaveCount(0);
});

test('lookupClues returns empty for short answers', function () {
    $user = User::factory()->create();
    $myPuzzle = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $instance = Livewire::test('pages::crosswords.editor', ['crossword' => $myPuzzle])->instance();
    $result = $instance->lookupClues('A');

    expect($result)->toHaveCount(0);
});

test('publishing a puzzle harvests clue entries', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['A', 'B', 'C']],
        'clues_across' => [['number' => 1, 'clue' => 'Start of alphabet']],
        'clues_down' => [],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished');

    expect(ClueEntry::count())->toBe(1)
        ->and(ClueEntry::first()->answer)->toBe('ABC')
        ->and(ClueEntry::first()->clue)->toBe('Start of alphabet');
});

test('unpublishing a puzzle purges clue entries', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['A', 'B', 'C']],
        'clues_across' => [['number' => 1, 'clue' => 'Start of alphabet']],
        'clues_down' => [],
    ]);

    ClueEntry::create([
        'answer' => 'ABC',
        'clue' => 'Start of alphabet',
        'crossword_id' => $crossword->id,
        'user_id' => $user->id,
        'direction' => 'across',
        'clue_number' => 1,
    ]);

    $this->actingAs($user);

    expect(ClueEntry::count())->toBe(1);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished');

    expect(ClueEntry::count())->toBe(0);
});
