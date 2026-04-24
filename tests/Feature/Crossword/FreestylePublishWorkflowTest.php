<?php

use App\Enums\PuzzleType;
use App\Models\Crossword;
use Livewire\Livewire;

// 3x3 fully-open grid is numbered (with minLength=2):
//   [1, 2, 3]
//   [4, 0, 0]
//   [5, 0, 0]
// because (0,0) starts both across+down, (0,1)/(0,2) start downs,
// (1,0)/(2,0) start additional acrosses.

test('convertEmptyCellsToVoid voids unfilled cells and renumbers', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [
            [1, 2, 3],
            [4, 0, 0],
            [5, 0, 0],
        ],
        'solution' => [
            ['C', 'A', 'T'],
            ['', '', ''],
            ['', '', ''],
        ],
        'clues_across' => [['number' => 1, 'clue' => 'Whiskered pet']],
        'clues_down' => [],
    ]);

    $crossword->convertEmptyCellsToVoid();
    $crossword->refresh();

    expect($crossword->grid[0])->toBe([1, 0, 0])
        ->and($crossword->grid[1])->toBe([null, null, null])
        ->and($crossword->grid[2])->toBe([null, null, null])
        ->and($crossword->solution[1])->toBe([null, null, null])
        ->and($crossword->clues_across)->toHaveCount(1)
        ->and($crossword->clues_across[0]['clue'])->toBe('Whiskered pet')
        ->and($crossword->clues_down)->toHaveCount(0);
});

test('restoreVoidCellsToEmpty turns voids back into empty playable cells', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [
            [1, 0, 0],
            [null, null, null],
            [null, null, null],
        ],
        'solution' => [
            ['C', 'A', 'T'],
            [null, null, null],
            [null, null, null],
        ],
        'clues_across' => [['number' => 1, 'clue' => 'Whiskered pet']],
        'clues_down' => [],
    ]);

    $crossword->restoreVoidCellsToEmpty();
    $crossword->refresh();

    expect($crossword->grid)->toBe([
        [1, 2, 3],
        [4, 0, 0],
        [5, 0, 0],
    ])
        ->and($crossword->solution[1])->toBe(['', '', ''])
        ->and(collect($crossword->clues_across)->pluck('clue')->all())
        ->toBe(['Whiskered pet', '', '']);
});

test('clue text follows its cell location after renumbering', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [
            ['C', 'A', 'T'],
            ['', '', ''],
            ['D', 'O', 'G'],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Whiskered pet'],
            ['number' => 4, 'clue' => 'Will be voided'],
            ['number' => 5, 'clue' => 'Canine'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Down 1'],
            ['number' => 2, 'clue' => 'Down 2'],
            ['number' => 3, 'clue' => 'Down 3'],
        ],
    ]);

    $crossword->convertEmptyCellsToVoid();
    $crossword->refresh();

    $byNum = collect($crossword->clues_across)->keyBy('number');

    expect($crossword->grid[2][0])->toBe(2)
        ->and($byNum[1]['clue'])->toBe('Whiskered pet')
        ->and($byNum[2]['clue'])->toBe('Canine')
        ->and($crossword->clues_across)->toHaveCount(2)
        ->and($crossword->clues_down)->toHaveCount(0);
});

test('lockFreestyleGrid voids empty cells and flips the lock flag', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['', '', ''], ['', '', '']],
        'clues_across' => [['number' => 1, 'clue' => 'Whiskered pet']],
        'clues_down' => [],
    ]);

    $this->actingAs($crossword->user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('lockFreestyleGrid')
        ->assertSet('freestyleLocked', true)
        ->assertDispatched('freestyle-locked', locked: true);

    $crossword->refresh();
    expect($crossword->freestyle_locked)->toBeTrue()
        ->and($crossword->grid[1])->toBe([null, null, null])
        ->and($crossword->is_published)->toBeFalse();
});

test('unlockFreestyleGrid restores voids and flips the lock flag back', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 0, 0], [null, null, null], [null, null, null]],
        'solution' => [['C', 'A', 'T'], [null, null, null], [null, null, null]],
        'clues_across' => [['number' => 1, 'clue' => 'Whiskered pet']],
        'clues_down' => [],
        'freestyle_locked' => true,
    ]);

    $this->actingAs($crossword->user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('unlockFreestyleGrid')
        ->assertSet('freestyleLocked', false)
        ->assertDispatched('freestyle-locked', locked: false);

    $crossword->refresh();
    expect($crossword->freestyle_locked)->toBeFalse()
        ->and($crossword->grid)->toBe([[1, 2, 3], [4, 0, 0], [5, 0, 0]])
        ->and($crossword->solution[1])->toBe(['', '', '']);
});

test('lockFreestyleGrid is a no-op for non-freestyle puzzles', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['', '', ''], ['', '', '']],
    ]);

    $this->actingAs($crossword->user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('lockFreestyleGrid');

    $crossword->refresh();
    expect($crossword->freestyle_locked)->toBeFalse()
        ->and($crossword->grid[1])->toBe([4, 0, 0]);
});

test('togglePublished no longer mutates the grid for any puzzle type', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'grid' => [[1, 2, 3], [4, 0, 0], [5, 0, 0]],
        'solution' => [['C', 'A', 'T'], ['', '', ''], ['', '', '']],
        'clues_across' => [['number' => 1, 'clue' => 'Whiskered pet']],
        'clues_down' => [],
    ]);

    $this->actingAs($crossword->user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished');

    $crossword->refresh();
    expect($crossword->is_published)->toBeTrue()
        ->and($crossword->grid)->toBe([[1, 2, 3], [4, 0, 0], [5, 0, 0]])
        ->and($crossword->solution[1])->toBe(['', '', '']);
});

test('completeness fill check passes for a partially-filled freestyle puzzle', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'solution' => [['C', 'A', 'T'], ['', '', ''], ['', '', '']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeTrue();
});

test('completeness fill check still requires a fully-filled standard puzzle', function () {
    $crossword = Crossword::factory()->create([
        'puzzle_type' => PuzzleType::Standard,
        'width' => 3,
        'height' => 3,
        'solution' => [['C', 'A', 'T'], ['', '', ''], ['', '', '']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeFalse();
});

test('completeness fill check rejects a freestyle puzzle with no letters at all', function () {
    $crossword = Crossword::factory()->freestyle()->create([
        'width' => 3,
        'height' => 3,
        'solution' => [['', '', ''], ['', '', ''], ['', '', '']],
    ]);

    expect($crossword->completeness()['checks']['fill'])->toBeFalse();
});
