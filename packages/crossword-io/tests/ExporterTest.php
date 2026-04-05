<?php

use Zorbl\CrosswordIO\Exporters\IpuzExporter;
use Zorbl\CrosswordIO\Exporters\JpzExporter;
use Zorbl\CrosswordIO\Exporters\PuzExporter;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\IpuzImporter;
use Zorbl\CrosswordIO\Importers\JpzImporter;
use Zorbl\CrosswordIO\Importers\PuzImporter;

describe('IpuzExporter', function () {
    it('exports a crossword to valid ipuz format', function () {
        $crossword = makeCrossword([
            'styles' => ['0,0' => ['shapebg' => 'circle']],
        ]);

        $exporter = new IpuzExporter;
        $ipuz = $exporter->export($crossword);

        expect($ipuz['version'])->toBe('https://ipuz.org/v2')
            ->and($ipuz['kind'])->toBe(['https://ipuz.org/crossword#1'])
            ->and($ipuz['dimensions'])->toBe(['width' => 3, 'height' => 3])
            ->and($ipuz['title'])->toBe('Test Puzzle')
            ->and($ipuz['author'])->toBe('Tester')
            ->and($ipuz['clues']['Across'])->toHaveCount(3)
            ->and($ipuz['clues']['Across'][0])->toBe([1, 'California'])
            ->and($ipuz['clues']['Down'][0])->toBe([1, 'Cowboy']);

        expect($ipuz['puzzle'][0][0])->toBe(['cell' => 1, 'style' => ['shapebg' => 'circle']]);
        expect($ipuz['puzzle'][0][1])->toBe(2);
    });

    it('exports to valid JSON string', function () {
        $crossword = makeCrossword();

        $exporter = new IpuzExporter;
        $json = $exporter->toJson($crossword);

        $decoded = json_decode($json, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE)
            ->and($decoded['version'])->toBe('https://ipuz.org/v2');
    });

    it('roundtrips through import and export', function () {
        $crossword = makeCrossword();

        $exporter = new IpuzExporter;
        $json = $exporter->toJson($crossword);

        $importer = new IpuzImporter(new GridNumberer);
        $result = $importer->import($json);

        expect($result['title'])->toBe('Test Puzzle')
            ->and($result['solution'])->toBe($crossword->solution)
            ->and($result['clues_across'])->toHaveCount(3)
            ->and($result['clues_down'])->toHaveCount(3);
    });
});

describe('PuzExporter', function () {
    it('exports a crossword to valid puz binary', function () {
        $crossword = makeCrossword();
        $numberer = new GridNumberer;

        $exporter = new PuzExporter($numberer);
        $binary = $exporter->export($crossword);

        expect(substr($binary, 0x02, 12))->toBe("ACROSS&DOWN\0")
            ->and(ord($binary[0x2C]))->toBe(3)
            ->and(ord($binary[0x2D]))->toBe(3);

        $solutionBoard = substr($binary, 52, 9);
        expect($solutionBoard)->toBe('CA.BOT.LO');
    });

    it('roundtrips through export and import', function () {
        $crossword = makeCrossword([
            'copyright' => '2024',
            'notes' => 'Some notes',
        ]);

        $numberer = new GridNumberer;
        $exporter = new PuzExporter($numberer);
        $importer = new PuzImporter($numberer);

        $binary = $exporter->export($crossword);
        $result = $importer->import($binary);

        expect($result['title'])->toBe('Test Puzzle')
            ->and($result['author'])->toBe('Tester')
            ->and($result['copyright'])->toBe('2024')
            ->and($result['notes'])->toBe('Some notes')
            ->and($result['solution'])->toBe($crossword->solution)
            ->and($result['clues_across'])->toHaveCount(3)
            ->and($result['clues_down'])->toHaveCount(3)
            ->and($result['clues_across'][0]['clue'])->toBe('California')
            ->and($result['clues_down'][0]['clue'])->toBe('Cowboy');
    });

    it('includes GEXT section when circles exist', function () {
        $crossword = makeCrossword([
            'styles' => [
                '0,0' => ['shapebg' => 'circle'],
                '1,2' => ['shapebg' => 'circle'],
            ],
        ]);

        $numberer = new GridNumberer;
        $exporter = new PuzExporter($numberer);
        $binary = $exporter->export($crossword);

        expect(str_contains($binary, 'GEXT'))->toBeTrue();

        $importer = new PuzImporter($numberer);
        $result = $importer->import($binary);

        expect($result['styles']['0,0'])->toBe(['shapebg' => 'circle'])
            ->and($result['styles']['1,2'])->toBe(['shapebg' => 'circle']);
    });
});

describe('JpzExporter', function () {
    it('exports a crossword to valid jpz XML', function () {
        $crossword = makeCrossword();
        $numberer = new GridNumberer;

        $exporter = new JpzExporter($numberer);
        $xml = $exporter->toXml($crossword);

        expect($xml)->toContain('rectangular-puzzle')
            ->and($xml)->toContain('Test Puzzle')
            ->and($xml)->toContain('Tester')
            ->and($xml)->toContain('solution="C"')
            ->and($xml)->toContain('type="block"')
            ->and($xml)->toContain('California')
            ->and($xml)->toContain('Across')
            ->and($xml)->toContain('Down');
    });

    it('produces gzip-compressed output from export()', function () {
        $crossword = makeCrossword();
        $numberer = new GridNumberer;

        $exporter = new JpzExporter($numberer);
        $compressed = $exporter->export($crossword);

        expect($compressed[0])->toBe("\x1f")
            ->and($compressed[1])->toBe("\x8b");

        $xml = gzdecode($compressed);
        expect($xml)->toContain('rectangular-puzzle');
    });

    it('roundtrips through export and import', function () {
        $crossword = makeCrossword([
            'copyright' => '2024',
            'notes' => 'Some notes',
        ]);

        $numberer = new GridNumberer;
        $exporter = new JpzExporter($numberer);
        $importer = new JpzImporter($numberer);

        $compressed = $exporter->export($crossword);
        $result = $importer->import($compressed);

        expect($result['title'])->toBe('Test Puzzle')
            ->and($result['author'])->toBe('Tester')
            ->and($result['copyright'])->toBe('2024')
            ->and($result['notes'])->toBe('Some notes')
            ->and($result['solution'])->toBe($crossword->solution)
            ->and($result['clues_across'])->toHaveCount(3)
            ->and($result['clues_down'])->toHaveCount(3)
            ->and($result['clues_across'][0]['clue'])->toBe('California')
            ->and($result['clues_down'][0]['clue'])->toBe('Cowboy');
    });

    it('includes circle styles in exported cells', function () {
        $crossword = makeCrossword([
            'styles' => ['0,0' => ['shapebg' => 'circle']],
        ]);

        $numberer = new GridNumberer;
        $exporter = new JpzExporter($numberer);
        $xml = $exporter->toXml($crossword);

        expect($xml)->toContain('background-shape="circle"');

        $importer = new JpzImporter($numberer);
        $result = $importer->import($xml);

        expect($result['styles']['0,0'])->toBe(['shapebg' => 'circle']);
    });
});
