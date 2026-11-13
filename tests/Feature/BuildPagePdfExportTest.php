<?php

use App\Enums\PdfLayout;
use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
use App\Services\PdfExporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('owner can export a single puzzle as PDF from the Build page card menu', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user, ['title' => 'Card Export']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->assertSet('pdfExportPuzzleId', $puzzle->id)
        ->assertSet('showPdfExportModal', true)
        ->set('pdfOrientation', 'landscape')
        ->call('confirmPdfExport')
        ->assertSet('showPdfExportModal', false)
        ->assertFileDownloaded('card-export.pdf');
});

test('cancelling the PDF export modal on the Build page resets its settings', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->set('pdfOrientation', 'landscape')
        ->set('pdfLayout', PdfLayout::GridTopRight->value)
        ->set('pdfNarrative', 'Some intro')
        ->call('cancelPdfExport')
        ->assertSet('showPdfExportModal', false)
        ->assertSet('pdfOrientation', 'portrait')
        ->assertSet('pdfLayout', PdfLayout::default()->value)
        ->assertSet('pdfNarrative', '');
});

test('a user cannot export another users unpublished puzzle from the Build page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherPuzzle = makePuzzle($otherUser, ['title' => 'Not Mine', 'is_published' => false]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $otherPuzzle->id)
        ->assertForbidden();
});

test('guest builders are prompted to sign up instead of exporting a PDF', function () {
    $anon = app(AnonymousUserManager::class)->create();
    $puzzle = makePuzzle($anon);

    $this->actingAs($anon);

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->assertSet('showPdfExportModal', false)
        ->assertSet('pdfExportPuzzleId', null)
        ->assertNoFileDownloaded();
});

test('the PDF preview renders the unsaved modal settings without saving them', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user, ['pdf_narrative' => 'Saved intro']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->set('pdfOrientation', 'landscape')
        ->set('pdfNarrative', 'Unsaved intro')
        ->call('pdfPreview')
        ->assertReturned(function (?string $encoded): bool {
            $pdf = base64_decode((string) $encoded);

            return str_starts_with($pdf, '%PDF') && str_contains($pdf, '792.000 612.000');
        })
        ->assertSet('showPdfExportModal', true)
        ->assertNoFileDownloaded();

    expect($puzzle->fresh()->pdf_narrative)->toBe('Saved intro');
});

test('the PDF is rendered in the layout chosen in the export modal', function (string $chosen, PdfLayout $rendered) {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    $this->mock(PdfExporter::class)
        ->shouldReceive('export')
        ->once()
        ->withArgs(fn (...$arguments): bool => in_array($rendered, $arguments, true))
        ->andReturn('%PDF-stub');

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->assertSet('pdfLayout', PdfLayout::default()->value)
        ->set('pdfLayout', $chosen)
        ->call('pdfPreview')
        ->assertReturned(base64_encode('%PDF-stub'));
})->with([
    'a wrap-around layout' => [PdfLayout::GridBottomLeft->value, PdfLayout::GridBottomLeft],
    'an unknown layout falls back to the standard one' => ['sideways', PdfLayout::Standard],
]);

test('the PDF preview returns nothing when the export modal is not open', function () {
    $user = User::factory()->create();
    makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('pdfPreview')
        ->assertReturned(null);
});

test('the PDF preview shows a newly uploaded header image before it is saved', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id);

    $withoutImage = strlen(base64_decode($component->call('pdfPreview')->effects['returns'][0]));

    $component
        ->set('pdfImage', UploadedFile::fake()->image('header.png', 200, 100))
        ->assertHasNoErrors()
        ->call('pdfPreview')
        ->assertReturned(fn (string $encoded): bool => strlen(base64_decode($encoded)) > $withoutImage);

    expect($puzzle->fresh()->pdf_image)->toBeNull();
});

test('uploading a header image that is not an image is rejected immediately', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->set('pdfImage', UploadedFile::fake()->create('notes.txt', 10, 'text/plain'))
        ->assertHasErrors(['pdfImage' => 'image'])
        ->call('pdfPreview')
        ->assertReturned(fn (?string $encoded): bool => str_starts_with(base64_decode((string) $encoded), '%PDF'));
});

/** The number of pages in a base64-encoded PDF, read from its page tree. */
function pdfPageCount(?string $encoded): int
{
    preg_match('/\/Type\s*\/Pages\b.*?\/Count\s+(\d+)/s', base64_decode((string) $encoded), $matches);

    return (int) ($matches[1] ?? 0);
}

test('the PDF gains an answer key page only when the builder asks for one', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.index')
        ->call('choosePdfExportFor', $puzzle->id)
        ->assertSet('pdfIncludeAnswerKey', false);

    $pagesWithoutKey = pdfPageCount($component->call('pdfPreview')->effects['returns'][0]);

    $component->set('pdfIncludeAnswerKey', true)
        ->call('pdfPreview')
        ->assertReturned(fn (string $encoded): bool => pdfPageCount($encoded) === $pagesWithoutKey + 1)
        ->call('confirmPdfExport')
        ->assertFileDownloaded();

    expect($pagesWithoutKey)->toBeGreaterThan(0);
});

test('a solver cannot get the answer key in a PDF by asking for it', function () {
    $user = User::factory()->create();
    $puzzle = makePuzzle($user, ['is_published' => true]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $puzzle])
        ->call('attemptExport', 'pdf')
        ->assertSet('showPdfExportModal', true)
        ->assertDontSee('Include answer key');

    $pagesWithoutKey = pdfPageCount($component->call('pdfPreview')->effects['returns'][0]);

    $component->set('pdfIncludeAnswerKey', true)
        ->call('pdfPreview')
        ->assertReturned(fn (string $encoded): bool => pdfPageCount($encoded) === $pagesWithoutKey);
});

it('offers the PDF layouts with grid lower right selected and grid above clues last', function () {
    expect(PdfLayout::default())->toBe(PdfLayout::GridBottomRight)
        ->and(array_map(fn (PdfLayout $layout): string => $layout->label(), PdfLayout::cases()))->toBe([
            'Grid upper left',
            'Grid upper right',
            'Grid lower left',
            'Grid lower right',
            'Grid above clues',
        ]);
});
