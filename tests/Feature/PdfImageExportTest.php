<?php

use App\Models\Crossword;
use App\Models\User;
use App\Services\PdfExporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders an image in the PDF when imagePath is provided', function () {
    Storage::fake('public');

    $imagePath = Storage::disk('public')->path('test-image.png');
    $image = UploadedFile::fake()->image('test-image.png', 200, 100);
    file_put_contents($imagePath, $image->getContent());

    $crossword = Crossword::factory()->make([
        'title' => 'Image PDF Test',
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
    $pdf = $exporter->export($crossword, imagePath: $imagePath);

    expect($pdf)->toStartWith('%PDF');
});

it('renders image data URI in the PDF HTML when imageDataUri is provided', function () {
    $html = view('exports.crossword-pdf', [
        'title' => 'Image Test',
        'author' => null,
        'copyright' => null,
        'notes' => null,
        'narrative' => null,
        'imageDataUri' => 'data:image/png;base64,iVBORw0KGgoAAAANS',
        'numberedGrid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => null,
        'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
        'cluesDown' => [['number' => 1, 'clue' => 'AC']],
        'styles' => [],
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
        'forceCluePageBreak' => false,
        'orientation' => 'portrait',
    ])->render();

    expect($html)
        ->toContain('class="pdf-image"')
        ->toContain('data:image/png;base64,iVBORw0KGgoAAAANS');
});

it('does not render image section when imageDataUri is null', function () {
    $html = view('exports.crossword-pdf', [
        'title' => 'No Image',
        'author' => null,
        'copyright' => null,
        'notes' => null,
        'narrative' => null,
        'imageDataUri' => null,
        'numberedGrid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => null,
        'cluesAcross' => [['number' => 1, 'clue' => 'AB']],
        'cluesDown' => [['number' => 1, 'clue' => 'AC']],
        'styles' => [],
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
        'forceCluePageBreak' => false,
        'orientation' => 'portrait',
    ])->render();

    expect($html)->not->toContain('class="pdf-image"');
});

it('does not render image when imagePath does not exist', function () {
    $crossword = Crossword::factory()->make([
        'title' => 'Missing Image',
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
    $pdf = $exporter->export($crossword, imagePath: '/nonexistent/path/image.png');

    expect($pdf)->toStartWith('%PDF');
});

it('stores uploaded PDF image and includes it in export', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Upload Image Test',
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

    $image = UploadedFile::fake()->image('puzzle-header.png', 400, 200);
    $path = $image->store('pdf-images', 'public');
    $crossword->update(['pdf_image' => $path]);

    Storage::disk('public')->assertExists($path);
    expect($crossword->fresh()->pdf_image)->toBe($path);
});

it('removes PDF image when pdfRemoveImage is set', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $image = UploadedFile::fake()->image('old-header.png', 400, 200);
    $path = $image->store('pdf-images', 'public');

    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Remove Image Test',
        'pdf_image' => $path,
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

    Storage::disk('public')->assertExists($path);

    Storage::disk('public')->delete($path);
    $crossword->update(['pdf_image' => null]);

    Storage::disk('public')->assertMissing($path);
    expect($crossword->fresh()->pdf_image)->toBeNull();
});
