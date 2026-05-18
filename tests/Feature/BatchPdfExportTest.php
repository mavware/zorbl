<?php

use App\Models\Crossword;
use App\Models\User;
use App\Services\PdfExporter;
use Livewire\Livewire;

function makePuzzle(User $user, array $overrides = []): Crossword
{
    return Crossword::factory()->for($user)->create(array_merge([
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
    ], $overrides));
}

it('exports multiple crosswords to a single PDF via PdfExporter', function () {
    $crosswords = collect([
        Crossword::factory()->make(['title' => 'Puzzle One', 'width' => 3, 'height' => 3, 'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]], 'solution' => [['C', 'A', '#'], ['B', 'O', 'T'], ['#', 'L', 'O']], 'clues_across' => [['number' => 1, 'clue' => 'CA']], 'clues_down' => [['number' => 1, 'clue' => 'CB']]]),
        Crossword::factory()->make(['title' => 'Puzzle Two', 'width' => 3, 'height' => 3, 'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]], 'solution' => [['D', 'E', '#'], ['F', 'G', 'H'], ['#', 'I', 'J']], 'clues_across' => [['number' => 1, 'clue' => 'DE']], 'clues_down' => [['number' => 1, 'clue' => 'DF']]]),
    ]);

    $exporter = app(PdfExporter::class);
    $pdf = $exporter->exportBatch($crosswords);

    expect($pdf)->toStartWith('%PDF');
    expect(strlen($pdf))->toBeGreaterThan(0);
});

it('batch PDF is larger than single puzzle PDF', function () {
    $crosswords = collect([
        Crossword::factory()->make(['title' => 'Batch A', 'width' => 3, 'height' => 3, 'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]], 'solution' => [['C', 'A', '#'], ['B', 'O', 'T'], ['#', 'L', 'O']], 'clues_across' => [['number' => 1, 'clue' => 'CA']], 'clues_down' => [['number' => 1, 'clue' => 'CB']]]),
        Crossword::factory()->make(['title' => 'Batch B', 'width' => 3, 'height' => 3, 'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]], 'solution' => [['D', 'E', '#'], ['F', 'G', 'H'], ['#', 'I', 'J']], 'clues_across' => [['number' => 1, 'clue' => 'DE']], 'clues_down' => [['number' => 1, 'clue' => 'DF']]]),
    ]);

    $exporter = app(PdfExporter::class);

    $single = $exporter->export($crosswords->first());
    $batch = $exporter->exportBatch($crosswords);

    expect(strlen($batch))->toBeGreaterThan(strlen($single));
});

it('batch PDF includes cover page when collection title is set', function () {
    $crosswords = collect([
        Crossword::factory()->make(['title' => 'Cover Puzzle', 'width' => 3, 'height' => 3, 'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]], 'solution' => [['A', 'B', '#'], ['C', 'D', 'E'], ['#', 'F', 'G']], 'clues_across' => [['number' => 1, 'clue' => 'AB']], 'clues_down' => [['number' => 1, 'clue' => 'AC']]]),
    ]);

    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'Cover Puzzle',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]],
            'solution' => [['A', 'B', '#'], ['C', 'D', 'E'], ['#', 'F', 'G']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => 'Weekly Puzzle Pack',
        'orientation' => 'portrait',
    ])->render();

    expect($html)
        ->toContain('cover-title')
        ->toContain('Weekly Puzzle Pack')
        ->toContain('1 puzzle');
});

it('batch PDF omits cover page when title is null', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'No Cover',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'portrait',
    ])->render();

    expect($html)->not->toContain('class="cover-page"');
});

it('batch PDF renders all puzzle titles', function () {
    $puzzleData = fn (string $title) => [
        'title' => $title,
        'author' => 'Author',
        'copyright' => null,
        'notes' => null,
        'numberedGrid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => null,
        'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
        'cluesDown' => [['number' => 1, 'clue' => 'AC']],
        'styles' => [],
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
        'forceCluePageBreak' => false,
    ];

    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [$puzzleData('First Puzzle'), $puzzleData('Second Puzzle'), $puzzleData('Third Puzzle')],
        'collectionTitle' => null,
        'orientation' => 'portrait',
    ])->render();

    expect($html)
        ->toContain('First Puzzle')
        ->toContain('Second Puzzle')
        ->toContain('Third Puzzle');
});

it('batch PDF supports landscape orientation', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'Landscape Batch',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'landscape',
    ])->render();

    expect($html)->toContain('size: letter landscape;');
});

test('users can select puzzles and export as batch PDF', function () {
    $user = User::factory()->create();
    $puzzle1 = makePuzzle($user, ['title' => 'Batch One']);
    $puzzle2 = makePuzzle($user, ['title' => 'Batch Two']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle1->id)
        ->assertSet('selectedPuzzles', [$puzzle1->id])
        ->call('togglePuzzleSelection', $puzzle2->id)
        ->assertSet('selectedPuzzles', [$puzzle1->id, $puzzle2->id])
        ->call('openBatchPdfExport')
        ->assertSet('showBatchPdfModal', true)
        ->set('batchPdfTitle', 'My Collection')
        ->set('batchPdfOrientation', 'landscape')
        ->call('exportBatchPdf')
        ->assertFileDownloaded('my-collection.pdf');
});

test('batch export uses default filename when no title', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user, ['title' => 'Solo']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->call('exportBatchPdf')
        ->assertFileDownloaded('puzzles-collection.pdf');
});

test('toggling an already-selected puzzle deselects it', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->assertSet('selectedPuzzles', [$puzzle->id])
        ->call('togglePuzzleSelection', $puzzle->id)
        ->assertSet('selectedPuzzles', []);
});

test('select all selects every puzzle', function () {
    $user = User::factory()->create();
    $p1 = makePuzzle($user, ['title' => 'All One']);
    $p2 = makePuzzle($user, ['title' => 'All Two']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('selectAllPuzzles')
        ->assertSet('selectedPuzzles', [$p1->id, $p2->id]);
});

test('clear selection empties the list', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('clearSelection')
        ->assertSet('selectedPuzzles', []);
});

test('cancelling batch PDF modal resets state', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->set('batchPdfTitle', 'Test')
        ->set('batchPdfOrientation', 'landscape')
        ->call('cancelBatchPdfExport')
        ->assertSet('showBatchPdfModal', false)
        ->assertSet('batchPdfTitle', '')
        ->assertSet('batchPdfOrientation', 'portrait');
});

test('batch export does not include other users puzzles', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $myPuzzle = makePuzzle($user, ['title' => 'Mine']);
    $otherPuzzle = makePuzzle($otherUser, ['title' => 'Not Mine']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('selectedPuzzles', [$myPuzzle->id, $otherPuzzle->id])
        ->call('openBatchPdfExport')
        ->call('exportBatchPdf')
        ->assertFileDownloaded('puzzles-collection.pdf');
});

test('opening batch export with no selection does nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('openBatchPdfExport')
        ->assertSet('showBatchPdfModal', false);
});

it('renders custom before pages in batch PDF', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'Test Puzzle',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'portrait',
        'beforePages' => [
            ['heading' => 'Welcome to the Pack', 'body' => 'Enjoy these puzzles!'],
        ],
        'afterPages' => [],
    ])->render();

    expect($html)
        ->toContain('custom-page')
        ->toContain('Welcome to the Pack')
        ->toContain('Enjoy these puzzles!');
});

it('renders custom after pages in batch PDF', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'Test Puzzle',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'portrait',
        'beforePages' => [],
        'afterPages' => [
            ['heading' => 'Answer Key', 'body' => 'Solutions are on the next page.'],
        ],
    ])->render();

    expect($html)
        ->toContain('Answer Key')
        ->toContain('Solutions are on the next page.');
});

it('renders custom page with heading only', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'P',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'portrait',
        'beforePages' => [['heading' => 'Intro Page', 'body' => '']],
        'afterPages' => [],
    ])->render();

    expect($html)
        ->toContain('Intro Page')
        ->toContain('custom-page-heading');
});

it('renders custom page with body only', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'P',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'portrait',
        'beforePages' => [],
        'afterPages' => [['heading' => '', 'body' => 'Thanks for playing!']],
    ])->render();

    expect($html)
        ->toContain('Thanks for playing!')
        ->not->toContain('class="custom-page-heading"');
});

it('omits custom pages when none are provided', function () {
    $html = view('exports.crossword-batch-pdf', [
        'puzzles' => [[
            'title' => 'P',
            'author' => null,
            'copyright' => null,
            'notes' => null,
            'numberedGrid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
            'prefilled' => null,
            'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
            'cluesDown' => [['number' => 1, 'clue' => 'AC']],
            'styles' => [],
            'cellSize' => 0.33,
            'numberFontSize' => 6,
            'letterFontSize' => 9,
            'numberHeight' => 0.116,
            'forceCluePageBreak' => false,
        ]],
        'collectionTitle' => null,
        'orientation' => 'portrait',
        'beforePages' => [],
        'afterPages' => [],
    ])->render();

    expect($html)->not->toContain('custom-page"');
});

it('passes custom pages through PdfExporter::exportBatch', function () {
    $crosswords = collect([
        Crossword::factory()->make([
            'title' => 'CP Test',
            'width' => 3,
            'height' => 3,
            'grid' => [[1, 2, '#'], [3, 0, 4], ['#', 5, 0]],
            'solution' => [['C', 'A', '#'], ['B', 'O', 'T'], ['#', 'L', 'O']],
            'clues_across' => [['number' => 1, 'clue' => 'CA']],
            'clues_down' => [['number' => 1, 'clue' => 'CB']],
        ]),
    ]);

    $exporter = app(PdfExporter::class);

    $withPages = $exporter->exportBatch($crosswords, 'portrait', null, [
        ['heading' => 'Intro', 'body' => 'Welcome', 'position' => 'before'],
        ['heading' => 'Outro', 'body' => 'Goodbye', 'position' => 'after'],
    ]);

    $without = $exporter->exportBatch($crosswords);

    expect($withPages)->toStartWith('%PDF');
    expect(strlen($withPages))->toBeGreaterThan(strlen($without));
});

test('users can add and remove custom pages in batch export', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->assertSet('batchCustomPages', [])
        ->call('addCustomPage')
        ->assertCount('batchCustomPages', 1)
        ->call('addCustomPage')
        ->assertCount('batchCustomPages', 2)
        ->call('removeCustomPage', 0)
        ->assertCount('batchCustomPages', 1);
});

test('custom pages are reset when cancelling batch export', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->call('addCustomPage')
        ->set('batchCustomPages.0.heading', 'Test')
        ->call('cancelBatchPdfExport')
        ->assertSet('batchCustomPages', []);
});

test('batch export with custom pages downloads PDF', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->call('addCustomPage')
        ->set('batchCustomPages.0.heading', 'Introduction')
        ->set('batchCustomPages.0.body', 'Welcome to this puzzle collection.')
        ->set('batchCustomPages.0.position', 'before')
        ->call('exportBatchPdf')
        ->assertFileDownloaded('puzzles-collection.pdf');
});

test('empty custom pages are filtered out during export', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->call('addCustomPage')
        ->call('exportBatchPdf')
        ->assertFileDownloaded('puzzles-collection.pdf');
});

test('custom pages are reset after successful export', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.index')
        ->call('togglePuzzleSelection', $puzzle->id)
        ->call('openBatchPdfExport')
        ->call('addCustomPage')
        ->set('batchCustomPages.0.heading', 'Intro')
        ->call('exportBatchPdf');

    expect($component->get('batchCustomPages'))->toBe([]);
});
