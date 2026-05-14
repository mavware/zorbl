<?php

use App\Models\Crossword;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

function makeExportProUser(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    return $user;
}

test('users can export their puzzle as ipuz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Export Me',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportIpuz')
        ->assertFileDownloaded('export-me.ipuz');
});

test('users can export their puzzle as puz', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Export Puz',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportPuz')
        ->assertFileDownloaded('export-puz.puz');
});

test('users can export their puzzle as jpz', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Export Jpz',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportJpz')
        ->assertFileDownloaded('export-jpz.jpz');
});

test('users can export a freestyle puzzle with void cells as pdf', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->freestyle()->for($user)->create([
        'title' => 'Freestyle Export',
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
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'Has'],
            ['number' => 2, 'clue' => 'IT'],
        ],
        'freestyle_locked' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportPdf')
        ->assertFileDownloaded('freestyle-export.pdf');
});

test('users can export a puzzle with cell background colors as pdf', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Colored Export',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('exportPdf')
        ->assertFileDownloaded('colored-export.pdf');
});

test('PDF export modal opens with correct defaults', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Modal Test',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'pdf')
        ->assertSet('showPdfExportModal', true)
        ->assertSet('pdfOrientation', 'portrait')
        ->assertSet('pdfNarrative', '');
});

test('PDF export modal pre-fills saved narrative text', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Saved Narrative',
        'pdf_narrative' => 'This puzzle explores the history of computing.',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'pdf')
        ->assertSet('showPdfExportModal', true)
        ->assertSet('pdfNarrative', 'This puzzle explores the history of computing.');
});

test('confirming PDF export saves narrative and downloads', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Narrative Export',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'pdf')
        ->set('pdfNarrative', 'Enjoy this themed puzzle!')
        ->set('pdfOrientation', 'landscape')
        ->call('confirmPdfExport')
        ->assertFileDownloaded('narrative-export.pdf');

    expect($crossword->fresh()->pdf_narrative)->toBe('Enjoy this themed puzzle!');
});

test('cancelling PDF export resets state', function () {
    $user = makeExportProUser();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Cancel Test',
        'pdf_narrative' => 'Original narrative',
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

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'pdf')
        ->set('pdfNarrative', 'Modified narrative')
        ->call('cancelPdfExport')
        ->assertSet('showPdfExportModal', false)
        ->assertSet('pdfOrientation', 'portrait')
        ->assertSet('pdfNarrative', '');

    expect($crossword->fresh()->pdf_narrative)->toBe('Original narrative');
});
