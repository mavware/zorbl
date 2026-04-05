<?php

use Zorbl\CrosswordIO\Exceptions\ExportValidationException;
use Zorbl\CrosswordIO\Exceptions\UnsupportedFeature;
use Zorbl\CrosswordIO\Exporters\IpuzExporter;
use Zorbl\CrosswordIO\Exporters\JpzExporter;
use Zorbl\CrosswordIO\Exporters\PuzExporter;
use Zorbl\CrosswordIO\GridNumberer;

describe('PuzExporter validation', function () {
    it('does not throw for a standard crossword', function () {
        $crossword = makeCrossword();
        $exporter = new PuzExporter(new GridNumberer);

        $exporter->validate($crossword);
    })->throwsNoExceptions();

    it('throws for void cells', function () {
        $crossword = makeCrossword([
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

        $exporter = new PuzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throws(ExportValidationException::class);

    it('throws for bars', function () {
        $crossword = makeCrossword([
            'styles' => ['0,0' => ['bars' => ['right']]],
        ]);

        $exporter = new PuzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throws(ExportValidationException::class);

    it('throws for non-ASCII in solution', function () {
        $crossword = makeCrossword([
            'solution' => [
                ["\u{0100}", 'A', '#'], // Ā (U+0100, above ISO-8859-1)
                ['B', 'O', 'T'],
                ['#', 'L', 'O'],
            ],
        ]);

        $exporter = new PuzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throws(ExportValidationException::class);

    it('throws for non-ASCII in clues', function () {
        $crossword = makeCrossword([
            'clues_across' => [
                ['number' => 1, 'clue' => "Caf\u{0113}"], // ē (U+0113)
                ['number' => 3, 'clue' => 'Robot'],
                ['number' => 5, 'clue' => 'Hello'],
            ],
        ]);

        $exporter = new PuzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throws(ExportValidationException::class);

    it('throws for non-ASCII in title', function () {
        $crossword = makeCrossword([
            'title' => "Puzzl\u{0151}", // ő (U+0151)
        ]);

        $exporter = new PuzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throws(ExportValidationException::class);

    it('does not throw for ISO-8859-1 characters', function () {
        $crossword = makeCrossword([
            'title' => 'Café', // é (U+00E9) is valid ISO-8859-1
            'solution' => [
                ['Ü', 'A', '#'], // Ü (U+00DC) is valid ISO-8859-1
                ['B', 'O', 'T'],
                ['#', 'L', 'O'],
            ],
        ]);

        $exporter = new PuzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throwsNoExceptions();

    it('reports multiple unsupported features', function () {
        $crossword = makeCrossword([
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
            'styles' => ['1,0' => ['bars' => ['right']]],
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

        try {
            $exporter = new PuzExporter(new GridNumberer);
            $exporter->validate($crossword);
            test()->fail('Expected ExportValidationException');
        } catch (ExportValidationException $e) {
            expect($e->format)->toBe('PUZ')
                ->and($e->unsupportedFeatures)->toContain(UnsupportedFeature::VoidCells)
                ->and($e->unsupportedFeatures)->toContain(UnsupportedFeature::Bars)
                ->and($e->unsupportedFeatures)->toHaveCount(2);
        }
    });

    it('allows lossy export with flag', function () {
        $crossword = makeCrossword([
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

        $exporter = new PuzExporter(new GridNumberer);
        $binary = $exporter->export($crossword, allowLossyExport: true);

        expect($binary)->toContain("ACROSS&DOWN\0");
    });
});

describe('JpzExporter validation', function () {
    it('does not throw for a standard crossword', function () {
        $crossword = makeCrossword();
        $exporter = new JpzExporter(new GridNumberer);

        $exporter->validate($crossword);
    })->throwsNoExceptions();

    it('throws for bars', function () {
        $crossword = makeCrossword([
            'styles' => ['0,0' => ['bars' => ['bottom']]],
        ]);

        $exporter = new JpzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throws(ExportValidationException::class);

    it('does not throw for void cells', function () {
        $crossword = makeCrossword([
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
        $exporter->validate($crossword);
    })->throwsNoExceptions();

    it('does not throw for non-ASCII', function () {
        $crossword = makeCrossword([
            'title' => "Puzzl\u{0151} Sp\u{0113}cial",
            'solution' => [
                ["\u{0100}", 'A', '#'],
                ['B', 'O', 'T'],
                ['#', 'L', 'O'],
            ],
        ]);

        $exporter = new JpzExporter(new GridNumberer);
        $exporter->validate($crossword);
    })->throwsNoExceptions();
});

describe('IpuzExporter validation', function () {
    it('does not throw for any combination of features', function () {
        $crossword = makeCrossword([
            'title' => "Puzzl\u{0151} Sp\u{0113}cial",
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
            'styles' => ['1,0' => ['bars' => ['right']]],
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
        ]);

        $exporter = new IpuzExporter;
        $result = $exporter->export($crossword);

        expect($result)->toBeArray()
            ->and($result['title'])->toBe("Puzzl\u{0151} Sp\u{0113}cial");
    });
});

describe('ExportValidationException', function () {
    it('builds a descriptive message', function () {
        $e = new ExportValidationException('PUZ', [
            UnsupportedFeature::VoidCells,
            UnsupportedFeature::Bars,
        ]);

        expect($e->format)->toBe('PUZ')
            ->and($e->unsupportedFeatures)->toHaveCount(2)
            ->and($e->getMessage())->toContain('PUZ')
            ->and($e->getMessage())->toContain('Void cells')
            ->and($e->getMessage())->toContain('Bar-style');
    });
});
