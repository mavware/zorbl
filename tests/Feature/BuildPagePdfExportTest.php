<?php

use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
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
        ->set('pdfNarrative', 'Some intro')
        ->call('cancelPdfExport')
        ->assertSet('showPdfExportModal', false)
        ->assertSet('pdfOrientation', 'portrait')
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
