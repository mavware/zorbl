<?php

use App\Models\Crossword;
use App\Services\PdfExporter;

it('exports a crossword to a valid PDF', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'PDF Test Puzzle',
        'author' => 'Test Author',
        'copyright' => '2026',
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

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword);

    // PDF files start with %PDF
    expect($pdf)->toStartWith('%PDF');
});

it('includes solution page when requested', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'With Solution',
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

    $exporter = app(PdfExporter::class);

    $withSolution = $exporter->export($crossword, includeSolution: true);
    $withoutSolution = $exporter->export($crossword, includeSolution: false);

    // The PDF with solution should be larger than without
    expect(strlen($withSolution))->toBeGreaterThan(strlen($withoutSolution));
});

it('handles large grids with dynamic cell sizing', function () {
    $width = 21;
    $height = 21;
    $grid = Crossword::emptyGrid($width, $height);
    $solution = Crossword::emptySolution($width, $height);

    // Fill with letters
    for ($r = 0; $r < $height; $r++) {
        for ($c = 0; $c < $width; $c++) {
            $solution[$r][$c] = chr(65 + (($r + $c) % 26));
        }
    }

    $crossword = Crossword::factory()->make([
        'title' => 'Large Grid',
        'width' => $width,
        'height' => $height,
        'grid' => $grid,
        'solution' => $solution,
        'clues_across' => [['number' => 1, 'clue' => 'Test clue']],
        'clues_down' => [['number' => 1, 'clue' => 'Test clue']],
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword);

    expect($pdf)->toStartWith('%PDF');
});

it('handles void cells in the grid', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Void Cells',
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
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'AD'],
            ['number' => 2, 'clue' => 'BE'],
        ],
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword);

    expect($pdf)->toStartWith('%PDF');
});

it('uses untitled puzzle when title is empty', function () {
    $crossword = Crossword::factory()->make([
        'title' => null,
        'author' => null,
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

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword);

    expect($pdf)->toStartWith('%PDF');
});
