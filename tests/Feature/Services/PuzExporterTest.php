<?php

use App\Models\Crossword;
use App\Services\PuzExporter;
use App\Services\PuzImporter;

it('exports a crossword to valid puz binary', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Test Export',
        'author' => 'Tester',
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
            ['number' => 1, 'clue' => 'California'],
            ['number' => 3, 'clue' => 'Robot helper'],
            ['number' => 5, 'clue' => 'Hello'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Cowboy'],
            ['number' => 2, 'clue' => 'All'],
            ['number' => 4, 'clue' => 'Also'],
        ],
    ]);

    $exporter = app(PuzExporter::class);
    $binary = $exporter->export($crossword);

    // Validate magic string
    expect(substr($binary, 0x02, 12))->toBe("ACROSS&DOWN\0");

    // Validate dimensions
    expect(ord($binary[0x2C]))->toBe(3)
        ->and(ord($binary[0x2D]))->toBe(3);

    // Validate solution board
    $solutionBoard = substr($binary, 52, 9);
    expect($solutionBoard)->toBe('CA.BOT.LO');
});

it('roundtrips through export and import', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Roundtrip',
        'author' => 'Author',
        'copyright' => '2024',
        'notes' => 'Some notes',
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
            ['number' => 1, 'clue' => 'California'],
            ['number' => 3, 'clue' => 'Robot helper'],
            ['number' => 5, 'clue' => 'Hello'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Cowboy'],
            ['number' => 2, 'clue' => 'All'],
            ['number' => 4, 'clue' => 'Also'],
        ],
    ]);

    $exporter = app(PuzExporter::class);
    $importer = app(PuzImporter::class);

    $binary = $exporter->export($crossword);
    $result = $importer->import($binary);

    expect($result['title'])->toBe('Roundtrip')
        ->and($result['author'])->toBe('Author')
        ->and($result['copyright'])->toBe('2024')
        ->and($result['notes'])->toBe('Some notes')
        ->and($result['width'])->toBe(3)
        ->and($result['height'])->toBe(3)
        ->and($result['solution'])->toBe($crossword->solution)
        ->and($result['clues_across'])->toHaveCount(3)
        ->and($result['clues_down'])->toHaveCount(3)
        ->and($result['clues_across'][0]['clue'])->toBe('California')
        ->and($result['clues_down'][0]['clue'])->toBe('Cowboy');
});

it('converts void cells to blocks in puz export', function () {
    $crossword = Crossword::factory()->make([
        'width' => 3,
        'height' => 3,
        'grid' => [
            [null, 1, 2],
            [3, 0, 0],
            [0, 0, null],
        ],
        'solution' => [
            [null, 'A', 'B'],
            ['C', 'D', 'E'],
            ['F', 'G', null],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'AB'],
            ['number' => 3, 'clue' => 'CDE'],
            ['number' => 4, 'clue' => 'FG'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'AD'],
            ['number' => 2, 'clue' => 'BE'],
            ['number' => 3, 'clue' => 'CF'],
        ],
    ]);

    $exporter = app(PuzExporter::class);
    $binary = $exporter->export($crossword);

    // Void cells should be '.' (blocks) in the solution board
    $solutionBoard = substr($binary, 52, 9);
    expect($solutionBoard[0])->toBe('.')
        ->and($solutionBoard[8])->toBe('.');
});

it('includes GEXT section when circles exist', function () {
    $crossword = Crossword::factory()->make([
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
        'styles' => [
            '0,0' => ['shapebg' => 'circle'],
            '1,2' => ['shapebg' => 'circle'],
        ],
    ]);

    $exporter = app(PuzExporter::class);
    $binary = $exporter->export($crossword);

    // GEXT section should be present
    expect(str_contains($binary, 'GEXT'))->toBeTrue();

    // Re-import and verify circles survived
    $importer = app(PuzImporter::class);
    $result = $importer->import($binary);

    expect($result['styles'])->not->toBeNull()
        ->and($result['styles']['0,0'])->toBe(['shapebg' => 'circle'])
        ->and($result['styles']['1,2'])->toBe(['shapebg' => 'circle']);
});
