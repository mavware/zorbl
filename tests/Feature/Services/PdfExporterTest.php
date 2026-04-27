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

it('exports freestyle puzzle with void cells as removed squares', function () {
    $crossword = Crossword::factory()->freestyle()->make([
        'title' => 'Freestyle PDF',
        'width' => 5,
        'height' => 5,
        'grid' => [
            [1, 2, 3, null, null],
            [4, 0, 0, null, null],
            [5, 0, 6, 7, 8],
            [null, null, 9, 0, 0],
            [null, null, 10, 0, 0],
        ],
        'solution' => [
            ['C', 'A', 'T', null, null],
            ['A', 'R', 'E', null, null],
            ['B', 'O', 'W', 'E', 'D'],
            [null, null, 'I', 'N', 'D'],
            [null, null, 'G', 'O', 'T'],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Feline'],
            ['number' => 4, 'clue' => 'Exist'],
            ['number' => 5, 'clue' => 'Bent over'],
            ['number' => 9, 'clue' => 'Type of'],
            ['number' => 10, 'clue' => 'Obtained'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Taxi'],
            ['number' => 2, 'clue' => 'Mineral'],
            ['number' => 3, 'clue' => 'Tie'],
            ['number' => 7, 'clue' => 'Finish'],
            ['number' => 8, 'clue' => 'Action'],
        ],
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword, includeSolution: true);

    expect($pdf)->toStartWith('%PDF');

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'prefilled' => $crossword->prefilled,
        'notes' => $crossword->notes,
        'includeSolution' => true,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)
        ->toContain('class="void"')
        ->toContain('Freestyle PDF');
});

it('renders cell background colors in PDF', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Styled Puzzle',
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
        'styles' => [
            '0,0' => ['color' => '#BAE6FD'],
            '1,1' => ['color' => '#E9D5FF', 'shapebg' => 'circle'],
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

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'prefilled' => $crossword->prefilled,
        'notes' => $crossword->notes,
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)
        ->toContain('background-color: #BAE6FD;')
        ->toContain('background-color: #E9D5FF;')
        ->toContain('class="circle"');
});

it('renders circles on cells with shapebg style', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Circle Puzzle',
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
        'styles' => [
            '0,0' => ['shapebg' => 'circle'],
            '2,1' => ['shapebg' => 'circle'],
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
    $pdf = $exporter->export($crossword, includeSolution: true);

    expect($pdf)->toStartWith('%PDF');

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'prefilled' => $crossword->prefilled,
        'notes' => $crossword->notes,
        'includeSolution' => true,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)->toContain('class="circle"');
});

it('renders bar-style word boundaries in PDF', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Barred Puzzle',
        'width' => 3,
        'height' => 3,
        'grid' => [
            [1, 2, 3],
            [4, 0, 0],
            [5, 0, 0],
        ],
        'solution' => [
            ['C', 'A', 'T'],
            ['A', 'R', 'E'],
            ['B', 'O', 'W'],
        ],
        'styles' => [
            '0,0' => ['bars' => ['right', 'bottom']],
            '1,2' => ['bars' => ['left']],
            '2,0' => ['bars' => ['top']],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'Feline'],
            ['number' => 4, 'clue' => 'Exist'],
            ['number' => 5, 'clue' => 'Archery tool'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Taxi'],
            ['number' => 2, 'clue' => 'Mineral'],
            ['number' => 3, 'clue' => 'Tower'],
        ],
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword, includeSolution: true);

    expect($pdf)->toStartWith('%PDF');

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'prefilled' => $crossword->prefilled,
        'notes' => $crossword->notes,
        'includeSolution' => true,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)
        ->toContain('bar-right')
        ->toContain('bar-bottom')
        ->toContain('bar-left')
        ->toContain('bar-top')
        ->toContain('Barred Puzzle');
});

it('renders bars on solution page too', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Barred Solution',
        'width' => 2,
        'height' => 2,
        'grid' => [
            [1, 2],
            [3, 0],
        ],
        'solution' => [
            ['A', 'B'],
            ['C', 'D'],
        ],
        'styles' => [
            '0,1' => ['bars' => ['bottom']],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'AB'],
            ['number' => 3, 'clue' => 'CD'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'AC'],
            ['number' => 2, 'clue' => 'BD'],
        ],
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->export($crossword, includeSolution: true);

    expect($pdf)->toStartWith('%PDF');

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'prefilled' => $crossword->prefilled,
        'notes' => $crossword->notes,
        'includeSolution' => true,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    // bar-bottom should appear in both grids (blank + solution) plus the CSS definition
    expect(substr_count($html, 'bar-bottom'))->toBe(3);
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

it('renders prefilled cells in the blank grid', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Prefilled PDF',
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
        'prefilled' => [
            ['C', '', '#'],
            ['', '', ''],
            ['#', 'L', ''],
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
    $pdf = $exporter->export($crossword, includeSolution: false);

    expect($pdf)->toStartWith('%PDF');

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'notes' => $crossword->notes,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'prefilled' => $crossword->prefilled,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)
        ->toContain('class="cell-letter prefilled"')
        ->toContain('C</div>')
        ->toContain('L</div>');
});

it('renders notes in the PDF header', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Notes Puzzle',
        'notes' => 'All theme answers relate to astronomy.',
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

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'notes' => $crossword->notes,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'prefilled' => $crossword->prefilled,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'includeSolution' => true,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)
        ->toContain('class="notes"')
        ->toContain('All theme answers relate to astronomy.');
});

it('does not render notes when null', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'No Notes',
        'notes' => null,
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'clues_across' => [
            ['number' => 1, 'clue' => 'AB'],
            ['number' => 3, 'clue' => 'CD'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'AC'],
            ['number' => 2, 'clue' => 'BD'],
        ],
    ]);

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'notes' => $crossword->notes,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'prefilled' => $crossword->prefilled,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)->not->toContain('class="notes"');
});

it('does not show prefilled styling when prefilled is null', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'No Prefill',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => null,
        'clues_across' => [
            ['number' => 1, 'clue' => 'AB'],
            ['number' => 3, 'clue' => 'CD'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'AC'],
            ['number' => 2, 'clue' => 'BD'],
        ],
    ]);

    $html = view('exports.crossword-pdf', [
        'title' => $crossword->title,
        'author' => $crossword->author,
        'copyright' => $crossword->copyright,
        'notes' => $crossword->notes,
        'numberedGrid' => $crossword->grid,
        'solution' => $crossword->solution,
        'prefilled' => $crossword->prefilled,
        'cluesAcross' => $crossword->clues_across,
        'cluesDown' => $crossword->clues_down,
        'styles' => $crossword->styles ?? [],
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    expect($html)->not->toContain('class="cell-letter prefilled"');
});
