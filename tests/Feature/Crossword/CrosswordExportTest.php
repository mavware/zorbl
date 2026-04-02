<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('users can export their puzzle as ipuz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Export Me',
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
            ['number' => 1, 'clue' => 'CA'],
            ['number' => 3, 'clue' => 'BOT'],
            ['number' => 5, 'clue' => 'LO'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'CB'],
            ['number' => 2, 'clue' => 'AOL'],
            ['number' => 4, 'clue' => 'TO'],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportIpuz')
        ->assertFileDownloaded('export-me.ipuz');
});

test('users can export their puzzle as puz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Export Puz',
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
            ['number' => 1, 'clue' => 'CA'],
            ['number' => 3, 'clue' => 'BOT'],
            ['number' => 5, 'clue' => 'LO'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'CB'],
            ['number' => 2, 'clue' => 'AOL'],
            ['number' => 4, 'clue' => 'TO'],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportPuz')
        ->assertFileDownloaded('export-puz.puz');
});

test('users can export their puzzle as jpz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Export Jpz',
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
            ['number' => 1, 'clue' => 'CA'],
            ['number' => 3, 'clue' => 'BOT'],
            ['number' => 5, 'clue' => 'LO'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'CB'],
            ['number' => 2, 'clue' => 'AOL'],
            ['number' => 4, 'clue' => 'TO'],
        ],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportJpz')
        ->assertFileDownloaded('export-jpz.jpz');
});
