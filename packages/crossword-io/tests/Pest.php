<?php

use Zorbl\CrosswordIO\Crossword;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function makeCrossword(array $overrides = []): Crossword
{
    return Crossword::fromArray(array_merge([
        'title' => 'Test Puzzle',
        'author' => 'Tester',
        'copyright' => null,
        'notes' => null,
        'width' => 3,
        'height' => 3,
        'kind' => 'https://ipuz.org/crossword#1',
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
            ['number' => 1, 'clue' => 'California'],
            ['number' => 3, 'clue' => 'Robot helper'],
            ['number' => 5, 'clue' => 'Hello'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Cowboy'],
            ['number' => 2, 'clue' => 'All'],
            ['number' => 4, 'clue' => 'Also'],
        ],
        'styles' => null,
        'metadata' => null,
    ], $overrides));
}
