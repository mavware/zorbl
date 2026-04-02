<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Services\ClueHarvester;

test('harvest extracts answer-clue pairs from a crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
        'grid' => [
            [1, 2, '#'],
            [3, 0, 4],
            ['#', 5, 0],
        ],
        'solution' => [
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'OR neighbor'],
            ['number' => 3, 'clue' => 'Droid'],
            ['number' => 5, 'clue' => 'Behold!'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => "Trucker's radio"],
            ['number' => 2, 'clue' => 'MSN competitor'],
            ['number' => 4, 'clue' => 'A preposition'],
        ],
    ]);

    $harvester = app(ClueHarvester::class);
    $harvester->harvest($crossword);

    expect(ClueEntry::count())->toBe(6);

    $botEntry = ClueEntry::where('answer', 'BOT')->where('direction', 'across')->first();
    expect($botEntry)->not->toBeNull()
        ->and($botEntry->clue)->toBe('Droid')
        ->and($botEntry->user_id)->toBe($user->id)
        ->and($botEntry->crossword_id)->toBe($crossword->id);

    $aolEntry = ClueEntry::where('answer', 'AOL')->where('direction', 'down')->first();
    expect($aolEntry)->not->toBeNull()
        ->and($aolEntry->clue)->toBe('MSN competitor');
});

test('harvest skips entries with incomplete solutions', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['A', '', 'C']],
        'clues_across' => [['number' => 1, 'clue' => 'Test clue']],
        'clues_down' => [],
    ]);

    $harvester = app(ClueHarvester::class);
    $harvester->harvest($crossword);

    expect(ClueEntry::count())->toBe(0);
});

test('harvest skips entries with empty clues', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['A', 'B', 'C']],
        'clues_across' => [['number' => 1, 'clue' => '']],
        'clues_down' => [],
    ]);

    $harvester = app(ClueHarvester::class);
    $harvester->harvest($crossword);

    expect(ClueEntry::count())->toBe(0);
});

test('harvest upserts on re-save without duplicating', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['A', 'B', 'C']],
        'clues_across' => [['number' => 1, 'clue' => 'Original clue']],
        'clues_down' => [],
    ]);

    $harvester = app(ClueHarvester::class);
    $harvester->harvest($crossword);

    expect(ClueEntry::count())->toBe(1)
        ->and(ClueEntry::first()->clue)->toBe('Original clue');

    // Update clue and re-harvest
    $crossword->update(['clues_across' => [['number' => 1, 'clue' => 'Updated clue']]]);
    $crossword->refresh();
    $harvester->harvest($crossword);

    expect(ClueEntry::count())->toBe(1)
        ->and(ClueEntry::first()->clue)->toBe('Updated clue');
});

test('purge removes all entries for a crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 1,
        'grid' => [[1, 0, 0]],
        'solution' => [['X', 'Y', 'Z']],
        'clues_across' => [['number' => 1, 'clue' => 'Some clue']],
        'clues_down' => [],
    ]);

    $harvester = app(ClueHarvester::class);
    $harvester->harvest($crossword);

    expect(ClueEntry::count())->toBe(1);

    $harvester->purge($crossword);

    expect(ClueEntry::count())->toBe(0);
});
