<?php

use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\ImportDetector;
use Zorbl\CrosswordIO\Importers\IpuzImporter;
use Zorbl\CrosswordIO\Importers\JpzImporter;
use Zorbl\CrosswordIO\Importers\PdfImporter;
use Zorbl\CrosswordIO\Importers\PuzImporter;

beforeEach(function () {
    $numberer = new GridNumberer;
    $this->detector = new ImportDetector(
        new IpuzImporter($numberer),
        new PuzImporter($numberer),
        new JpzImporter($numberer),
        new PdfImporter($numberer),
    );
});

it('detects ipuz by extension', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[1, 2], [0, 0]],
    ]);

    $result = $this->detector->import($ipuz, 'ipuz');

    expect($result['width'])->toBe(2)
        ->and($result['height'])->toBe(2);
});

it('detects ipuz by content sniffing', function () {
    $ipuz = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[1, 2], [0, 0]],
    ]);

    $result = $this->detector->import($ipuz);

    expect($result['width'])->toBe(2);
});

it('strips UTF-8 BOM before importing', function () {
    $ipuz = "\xEF\xBB\xBF".json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 2, 'height' => 2],
        'puzzle' => [[1, 2], [0, 0]],
    ]);

    $result = $this->detector->import($ipuz, 'ipuz');

    expect($result['width'])->toBe(2);
});

it('detects pdf by extension', function () {
    $contents = file_get_contents(__DIR__.'/fixtures/march.pdf');
    $result = $this->detector->import($contents, 'pdf');

    expect($result['width'])->toBe(15)
        ->and($result['height'])->toBe(15);
});

it('detects pdf by content sniffing', function () {
    $contents = file_get_contents(__DIR__.'/fixtures/march.pdf');
    $result = $this->detector->import($contents);

    expect($result['width'])->toBe(15);
});
