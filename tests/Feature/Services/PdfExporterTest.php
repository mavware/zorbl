<?php

use App\Models\Crossword;
use App\Services\PdfExporter;
use Zorbl\CrosswordIO\GridNumberer;

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

it('exports a freestyle puzzle with scattered void cells', function () {
    $crossword = Crossword::factory()->freestyle()->make([
        'title' => 'Freestyle Void Test',
        'width' => 4,
        'height' => 4,
        'grid' => [
            [1, 2, null, null],
            [3, 0, 4, 0],
            [null, null, 5, 0],
            [null, null, 6, 0],
        ],
        'solution' => [
            ['H', 'I', null, null],
            ['A', 'T', 'O', 'P'],
            [null, null, 'N', 'E'],
            [null, null, 'E', 'T'],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Greeting'],
            ['number' => 3, 'clue' => 'Upon'],
            ['number' => 5, 'clue' => 'A person'],
            ['number' => 6, 'clue' => 'A pet'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Has'],
            ['number' => 2, 'clue' => 'Information technology'],
            ['number' => 4, 'clue' => 'Single'],
            ['number' => 5, 'clue' => 'Snare'],
        ],
        'freestyle_locked' => true,
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword);

    expect($pdf)->toStartWith('%PDF');
});

it('renders cell background colors in the PDF view', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Colored Cells',
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
            '0,0' => ['shapebg' => '#FECACA'],
            '1,2' => ['shapebg' => '#BAE6FD'],
        ],
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword);

    expect($pdf)->toStartWith('%PDF');
});

it('renders void cells and background colors in the HTML output', function () {
    $numberer = app(GridNumberer::class);
    $grid = [
        [1, 2, null],
        [3, 0, 0],
        [null, 4, 0],
    ];
    $styles = [
        '0,0' => ['shapebg' => '#FECACA'],
        '1,1' => ['shapebg' => '#BAE6FD'],
    ];
    $result = $numberer->number($grid, 3, 3, $styles);

    $html = view('exports.crossword-pdf', [
        'title' => 'Test',
        'author' => 'Author',
        'copyright' => null,
        'numberedGrid' => $result['grid'],
        'solution' => [
            ['A', 'B', null],
            ['C', 'D', 'E'],
            [null, 'F', 'G'],
        ],
        'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
        'cluesDown' => [['number' => 1, 'clue' => 'AC']],
        'includeSolution' => true,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
        'styles' => $styles,
    ])->render();

    expect($html)
        ->toContain('class="void"')
        ->toContain('background-color: #FECACA')
        ->toContain('background-color: #BAE6FD');
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
