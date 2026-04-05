<?php

use App\Models\Crossword;
use Zorbl\CrosswordIO\Crossword as CrosswordDTO;
use Zorbl\CrosswordIO\Exporters\IpuzExporter;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\IpuzImporter;

it('exports a crossword to valid ipuz format', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Export Test',
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
            ['number' => 1, 'clue' => 'OR neighbor'],
            ['number' => 3, 'clue' => 'Droid'],
            ['number' => 5, 'clue' => 'Behold!'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => "Trucker's radio"],
            ['number' => 2, 'clue' => 'MSN competitor'],
            ['number' => 4, 'clue' => 'A preposition'],
        ],
        'styles' => ['0,0' => ['shapebg' => 'circle']],
    ]);

    $exporter = new IpuzExporter;
    $ipuz = $exporter->export($crossword->toCrosswordIO());

    expect($ipuz['version'])->toBe('https://ipuz.org/v2')
        ->and($ipuz['kind'])->toBe(['http://ipuz.org/crossword#1'])
        ->and($ipuz['dimensions'])->toBe(['width' => 3, 'height' => 3])
        ->and($ipuz['title'])->toBe('Export Test')
        ->and($ipuz['author'])->toBe('Tester')
        ->and($ipuz['clues']['Across'])->toHaveCount(3)
        ->and($ipuz['clues']['Across'][0])->toBe([1, 'OR neighbor'])
        ->and($ipuz['clues']['Down'][0])->toBe([1, "Trucker's radio"]);

    // Cell with style should be a dict
    expect($ipuz['puzzle'][0][0])->toBe(['cell' => 1, 'style' => ['shapebg' => 'circle']]);

    // Cell without style should be plain value
    expect($ipuz['puzzle'][0][1])->toBe(2);
});

it('roundtrips through import and export', function () {
    $originalIpuz = json_encode([
        'version' => 'https://ipuz.org/v2',
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
            'Across' => [[1, 'CA'], [3, 'BOT'], [5, 'LO']],
            'Down' => [[1, 'CB'], [2, 'AOL'], [4, 'TO']],
        ],
        'title' => 'Roundtrip Test',
    ]);

    $importer = new IpuzImporter(new GridNumberer);
    $imported = $importer->import($originalIpuz);

    $dto = CrosswordDTO::fromArray($imported);

    $exporter = new IpuzExporter;
    $exported = $exporter->export($dto);

    expect($exported['dimensions'])->toBe(['width' => 3, 'height' => 3])
        ->and($exported['title'])->toBe('Roundtrip Test')
        ->and($exported['solution'])->toBe([
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ])
        ->and($exported['clues']['Across'])->toHaveCount(3)
        ->and($exported['clues']['Down'])->toHaveCount(3);
});

it('exports to valid JSON string', function () {
    $crossword = Crossword::factory()->make([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [0, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [['number' => 1, 'clue' => 'Test']],
        'clues_down' => [['number' => 1, 'clue' => 'Test']],
    ]);

    $exporter = new IpuzExporter;
    $json = $exporter->toJson($crossword->toCrosswordIO());

    $decoded = json_decode($json, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['version'])->toBe('https://ipuz.org/v2');
});

it('does not throw for crosswords with bars, void cells, and non-ASCII', function () {
    $crossword = Crossword::factory()->make([
        'width' => 3,
        'height' => 3,
        'grid' => [
            [null, 1, 2],
            [3, 0, 0],
            [0, 0, null],
        ],
        'solution' => [
            [null, "\u{0100}", 'B'],
            ['C', 'D', 'E'],
            ['F', 'G', null],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => "\u{0100}B"],
            ['number' => 3, 'clue' => 'CDE'],
            ['number' => 4, 'clue' => 'FG'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => "\u{0100}D"],
            ['number' => 2, 'clue' => 'BE'],
            ['number' => 3, 'clue' => 'CF'],
        ],
        'styles' => ['1,0' => ['bars' => ['right']]],
    ]);

    $exporter = new IpuzExporter;
    $result = $exporter->export($crossword->toCrosswordIO());

    expect($result)->toBeArray()
        ->and($result['dimensions'])->toBe(['width' => 3, 'height' => 3]);
});
