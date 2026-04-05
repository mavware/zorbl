<?php

use App\Models\Crossword;
use Zorbl\CrosswordIO\Exceptions\ExportValidationException;
use Zorbl\CrosswordIO\Exporters\JpzExporter;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\JpzImporter;

it('exports a crossword to valid jpz XML', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Test JPZ Export',
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

    $exporter = new JpzExporter(new GridNumberer);
    $xml = $exporter->toXml($crossword->toCrosswordIO());

    expect($xml)->toContain('rectangular-puzzle')
        ->and($xml)->toContain('Test JPZ Export')
        ->and($xml)->toContain('Tester')
        ->and($xml)->toContain('solution="C"')
        ->and($xml)->toContain('type="block"')
        ->and($xml)->toContain('California')
        ->and($xml)->toContain('Across')
        ->and($xml)->toContain('Down');
});

it('produces gzip-compressed output from export()', function () {
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
    ]);

    $exporter = new JpzExporter(new GridNumberer);
    $compressed = $exporter->export($crossword->toCrosswordIO());

    // Should start with gzip magic bytes
    expect($compressed[0])->toBe("\x1f")
        ->and($compressed[1])->toBe("\x8b");

    // Should decompress to valid XML
    $xml = gzdecode($compressed);
    expect($xml)->toContain('rectangular-puzzle');
});

it('exports void cells as type void', function () {
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

    $exporter = new JpzExporter(new GridNumberer);
    $xml = $exporter->toXml($crossword->toCrosswordIO());

    expect($xml)->toContain('type="void"');
});

it('roundtrips through export and import', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Roundtrip JPZ',
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

    $numberer = new GridNumberer;
    $exporter = new JpzExporter($numberer);
    $importer = new JpzImporter($numberer);

    $compressed = $exporter->export($crossword->toCrosswordIO());
    $result = $importer->import($compressed);

    expect($result['title'])->toBe('Roundtrip JPZ')
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

it('includes circle styles in exported cells', function () {
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
        ],
    ]);

    $numberer = new GridNumberer;
    $exporter = new JpzExporter($numberer);
    $xml = $exporter->toXml($crossword->toCrosswordIO());

    expect($xml)->toContain('background-shape="circle"');

    // Roundtrip to verify circles survive
    $importer = new JpzImporter($numberer);
    $result = $importer->import($xml);

    expect($result['styles']['0,0'])->toBe(['shapebg' => 'circle']);
});

it('throws ExportValidationException for bars', function () {
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
            '0,1' => ['bars' => ['right']],
        ],
    ]);

    $exporter = new JpzExporter(new GridNumberer);
    $exporter->export($crossword->toCrosswordIO());
})->throws(ExportValidationException::class);
