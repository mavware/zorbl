<?php

use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\ImportDetector;
use Zorbl\CrosswordIO\Importers\IpuzImporter;
use Zorbl\CrosswordIO\Importers\JpzImporter;
use Zorbl\CrosswordIO\Importers\PuzImporter;

test('detects ipuz from extension', function () {
    $ipuzContent = json_encode([
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
            'Across' => [[1, 'Clue 1A'], [3, 'Clue 3A'], [5, 'Clue 5A']],
            'Down' => [[1, 'Clue 1D'], [2, 'Clue 2D'], [4, 'Clue 4D']],
        ],
    ]);

    $numberer = new GridNumberer;
    $detector = new ImportDetector(new IpuzImporter($numberer), new PuzImporter($numberer), new JpzImporter($numberer));
    $result = $detector->import($ipuzContent, 'ipuz');

    expect($result['width'])->toBe(3)
        ->and($result['height'])->toBe(3);
});

test('detects ipuz from content when no extension provided', function () {
    $ipuzContent = json_encode([
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
            'Across' => [[1, 'Clue 1A'], [3, 'Clue 3A'], [5, 'Clue 5A']],
            'Down' => [[1, 'Clue 1D'], [2, 'Clue 2D'], [4, 'Clue 4D']],
        ],
    ]);

    $numberer = new GridNumberer;
    $detector = new ImportDetector(new IpuzImporter($numberer), new PuzImporter($numberer), new JpzImporter($numberer));
    $result = $detector->import($ipuzContent, '');

    expect($result['width'])->toBe(3);
});

test('strips UTF-8 BOM from content', function () {
    $bom = "\xEF\xBB\xBF";
    $ipuzContent = $bom.json_encode([
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
            'Across' => [[1, 'Clue 1A'], [3, 'Clue 3A'], [5, 'Clue 5A']],
            'Down' => [[1, 'Clue 1D'], [2, 'Clue 2D'], [4, 'Clue 4D']],
        ],
    ]);

    $numberer = new GridNumberer;
    $detector = new ImportDetector(new IpuzImporter($numberer), new PuzImporter($numberer), new JpzImporter($numberer));
    $result = $detector->import($ipuzContent, 'json');

    expect($result['width'])->toBe(3);
});

test('falls back to content sniffing when extension is wrong', function () {
    $ipuzContent = json_encode([
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
            'Across' => [[1, 'Clue 1A'], [3, 'Clue 3A'], [5, 'Clue 5A']],
            'Down' => [[1, 'Clue 1D'], [2, 'Clue 2D'], [4, 'Clue 4D']],
        ],
    ]);

    // Pass wrong extension (.puz) but content is actually iPUZ JSON
    $numberer = new GridNumberer;
    $detector = new ImportDetector(new IpuzImporter($numberer), new PuzImporter($numberer), new JpzImporter($numberer));
    $result = $detector->import($ipuzContent, 'puz');

    expect($result['width'])->toBe(3);
});

test('throws exception for completely invalid content', function () {
    $numberer = new GridNumberer;
    $detector = new ImportDetector(new IpuzImporter($numberer), new PuzImporter($numberer), new JpzImporter($numberer));
    $detector->import('not a valid puzzle file at all', 'txt');
})->throws(Exception::class);
