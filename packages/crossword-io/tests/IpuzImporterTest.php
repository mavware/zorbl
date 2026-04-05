<?php

use Zorbl\CrosswordIO\Exceptions\IpuzImportException;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\IpuzImporter;

beforeEach(function () {
    $this->importer = new IpuzImporter(new GridNumberer);
});

it('imports a valid ipuz file', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 3, 'height' => 3],
        'puzzle' => [
            [1, 2, '#'],
            [3, 0, 4],
            ['#', 5, 0],
        ],
        'solution' => [
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ],
        'clues' => [
            'Across' => [
                [1, 'OR neighbor'],
                [3, 'Droid'],
                [5, 'Behold!'],
            ],
            'Down' => [
                [1, "Trucker's radio"],
                [2, 'MSN competitor'],
                [4, 'A preposition'],
            ],
        ],
        'title' => 'Test Puzzle',
        'author' => 'Test Author',
    ]);

    $result = $this->importer->import($ipuz);

    expect($result['title'])->toBe('Test Puzzle')
        ->and($result['author'])->toBe('Test Author')
        ->and($result['width'])->toBe(3)
        ->and($result['height'])->toBe(3)
        ->and($result['grid'][0][2])->toBe('#')
        ->and($result['grid'][2][0])->toBe('#')
        ->and($result['solution'][0][0])->toBe('C')
        ->and($result['solution'][1][2])->toBe('T')
        ->and($result['clues_across'])->toHaveCount(3)
        ->and($result['clues_across'][0]['clue'])->toBe('OR neighbor')
        ->and($result['clues_down'])->toHaveCount(3)
        ->and($result['clues_down'][0]['clue'])->toBe("Trucker's radio");
});

it('strips ipuz callback wrapper', function () {
    $inner = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[1, 2], [0, 0]],
    ]);

    $wrapped = "ipuz({$inner})";

    $result = $this->importer->import($wrapped);

    expect($result['width'])->toBe(2)
        ->and($result['height'])->toBe(2);
});

it('handles dict-format cells with styles', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [
            [['cell' => 1, 'style' => ['shapebg' => 'circle']], 2],
            [3, 0],
        ],
    ]);

    $result = $this->importer->import($ipuz);

    expect($result['styles'])->toHaveKey('0,0')
        ->and($result['styles']['0,0']['shapebg'])->toBe('circle')
        ->and($result['grid'][0][0])->toBe(1);
});

it('throws on invalid JSON', function () {
    $this->importer->import('not json{{{');
})->throws(IpuzImportException::class, 'Invalid JSON');

it('throws on missing version', function () {
    $this->importer->import(json_encode([
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[0, 0], [0, 0]],
    ]));
})->throws(IpuzImportException::class, 'version');

it('throws on mismatched dimensions', function () {
    $this->importer->import(json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 3, 'height' => 3],
        'puzzle' => [[0, 0], [0, 0]],
    ]));
})->throws(IpuzImportException::class, 'rows');

it('handles null (void) cells in the grid', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 3, 'height' => 3],
        'puzzle' => [
            [null, 1, null],
            [2, 0, 0],
            [null, 3, null],
        ],
        'solution' => [
            [null, 'A', null],
            ['B', 'C', 'D'],
            [null, 'E', null],
        ],
        'clues' => [
            'Across' => [[1, 'Across clue 1'], [2, 'Across clue 2']],
            'Down' => [[1, 'Down clue 1'], [3, 'Down clue 3']],
        ],
    ]);

    $result = $this->importer->import($ipuz);

    expect($result['grid'][0][0])->toBeNull()
        ->and($result['grid'][0][2])->toBeNull()
        ->and($result['solution'][0][0])->toBeNull()
        ->and($result['solution'][1][1])->toBe('C');
});

it('handles label-based clue format with cells array', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 5, 'height' => 1],
        'puzzle' => [[1, 0, 0, 0, 0]],
        'solution' => [['H', 'E', 'L', 'L', 'O']],
        'clues' => [
            'Across' => [
                ['label' => '1-2', 'clue' => 'A greeting', 'cells' => [[0, 0], [1, 0], [2, 0], [3, 0], [4, 0]]],
            ],
            'Down' => [],
        ],
    ]);

    $result = $this->importer->import($ipuz);

    expect($result['clues_across'])->toHaveCount(1)
        ->and($result['clues_across'][0]['clue'])->toBe('A greeting')
        ->and($result['clues_across'][0]['number'])->toBe(1);
});

it('preserves extra metadata fields', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[1, 2], [0, 0]],
        'publisher' => 'Test Publisher',
        'date' => '03/31/2026',
        'difficulty' => 'Easy',
    ]);

    $result = $this->importer->import($ipuz);

    expect($result['metadata'])->toHaveKey('publisher', 'Test Publisher')
        ->and($result['metadata'])->toHaveKey('date', '03/31/2026')
        ->and($result['metadata'])->toHaveKey('difficulty', 'Easy');
});

it('returns null for empty optional fields', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[1, 2], [0, 0]],
    ]);

    $result = $this->importer->import($ipuz);

    expect($result['title'])->toBeNull()
        ->and($result['author'])->toBeNull()
        ->and($result['copyright'])->toBeNull()
        ->and($result['notes'])->toBeNull()
        ->and($result['styles'])->toBeNull();
});

it('preserves arbitrary numbering as custom numbers in styles', function () {
    // Cell at (0,0) has number 42 instead of the auto-computed 1
    // Cell at (1,0) has number 99 instead of the auto-computed 3
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 3, 'height' => 3],
        'puzzle' => [
            [42, 2, '#'],
            [99, 0, 4],
            ['#', 5, 0],
        ],
        'solution' => [
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ],
        'clues' => [
            'Across' => [[1, 'California'], [3, 'Robot helper'], [5, 'Hello']],
            'Down' => [[1, 'Cowboy'], [2, 'All'], [4, 'Also']],
        ],
    ]);

    $result = $this->importer->import($ipuz);

    // Auto-numbering should be applied to the grid
    expect($result['grid'][0][0])->toBe(1)
        ->and($result['grid'][1][0])->toBe(3);

    // Custom numbers should be preserved in styles
    expect($result['styles']['0,0']['number'])->toBe(42)
        ->and($result['styles']['1,0']['number'])->toBe(99);

    // Cells with matching numbers should not get custom numbers
    expect($result['styles'])->not->toHaveKey('0,1')
        ->and($result['styles'])->not->toHaveKey('1,2');
});
