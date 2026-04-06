<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('users can import a valid ipuz file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ipuzContent = json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 3, 'height' => 3],
        'title' => 'Imported Puzzle',
        'puzzle' => [
            [1, 2, '#'],
            [3, 0, 4],
            ['#', 5, 0],
        ],
        'solution' => [
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ],
        'clues' => [
            'Across' => [[1, 'Clue 1A'], [3, 'Clue 3A'], [5, 'Clue 5A']],
            'Down' => [[1, 'Clue 1D'], [2, 'Clue 2D'], [4, 'Clue 4D']],
        ],
    ]);

    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', $ipuzContent);

    Livewire::test('pages::crosswords.index')
        ->set('importFile', $file)
        ->call('importPuzzle')
        ->assertRedirect();

    expect($user->crosswords()->count())->toBe(1);

    $crossword = $user->crosswords()->first();
    expect($crossword->title)->toBe('Imported Puzzle')
        ->and($crossword->width)->toBe(3)
        ->and($crossword->height)->toBe(3)
        ->and($crossword->solution[0][0])->toBe('C');
});

test('importing a diamond-shaped ipuz with null cells preserves voids and maps label-based clues', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $file = UploadedFile::fake()->createWithContent('example.ipuz', file_get_contents(base_path('example.ipuz')));

    Livewire::test('pages::crosswords.index')
        ->set('importFile', $file)
        ->call('importPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();

    expect($crossword->width)->toBe(13)
        ->and($crossword->height)->toBe(13)
        ->and($crossword->grid[0][0])->toBeNull()
        ->and($crossword->grid[0][6])->toBeInt()
        ->and($crossword->solution[0][6])->toBe('R')
        ->and($crossword->solution[0][0])->toBeNull()
        ->and($crossword->clues_across)->not->toBeEmpty()
        ->and($crossword->clues_down)->not->toBeEmpty();

    // The second across slot maps to "What bargain hunters enjoy" (SALES)
    // The first slot (FUN) has no clue in the original puzzle
    $salesClue = collect($crossword->clues_across)->firstWhere('number', 4);
    expect($salesClue['clue'])->toBe('What bargain hunters enjoy');
});

test('users can import a valid puz file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Build a minimal .puz binary: 3x3 grid, CA./BOT/.LO
    $solutionBoard = 'CA.BOT.LO';
    $playerState = '--.---.--';
    // Clues in .puz order: 1A, 1D, 2D, 3A, 4D, 5A
    $clues = ['California', 'Cowboy', 'All', 'Robot', 'Also', 'Hello'];

    $numClues = count($clues);
    $cib = pack('CCvvv', 3, 3, $numClues, 0x0001, 0);

    $header = pack('v', 0);                      // overall checksum (skip)
    $header .= "ACROSS&DOWN\0";                  // magic
    $header .= pack('v', 0);                     // CIB checksum (skip)
    $header .= str_repeat("\0", 8);              // masked checksums
    $header .= "1.3\0";                          // version
    $header .= "\0\0";                           // reserved
    $header .= "\0\0";                           // scrambled checksum
    $header .= str_repeat("\0", 12);             // reserved
    $header .= $cib;

    $stringTable = "Test Puz\0Author\0\0";
    foreach ($clues as $clue) {
        $stringTable .= $clue."\0";
    }
    $stringTable .= "\0"; // notes

    $binary = $header.$solutionBoard.$playerState.$stringTable;

    $file = UploadedFile::fake()->createWithContent('puzzle.puz', $binary);

    Livewire::test('pages::crosswords.index')
        ->set('importFile', $file)
        ->call('importPuzzle')
        ->assertRedirect();

    expect($user->crosswords()->count())->toBe(1);

    $crossword = $user->crosswords()->first();
    expect($crossword->title)->toBe('Test Puz')
        ->and($crossword->width)->toBe(3)
        ->and($crossword->height)->toBe(3)
        ->and($crossword->solution[0][0])->toBe('C');
});

test('users can import a valid jpz file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ns = 'http://crossword.info/xml/rectangular-puzzle';
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rectangular-puzzle xmlns="{$ns}">
    <metadata><title>Test JPZ</title><creator>JPZ Author</creator></metadata>
    <crossword>
        <grid width="3" height="3">
            <grid-look numbering-scheme="normal" cell-size-in-pixels="21" />
            <cell x="1" y="1" solution="C" number="1" />
            <cell x="2" y="1" solution="A" number="2" />
            <cell x="3" y="1" type="block" />
            <cell x="1" y="2" solution="B" number="3" />
            <cell x="2" y="2" solution="O" />
            <cell x="3" y="2" solution="T" number="4" />
            <cell x="1" y="3" type="block" />
            <cell x="2" y="3" solution="L" number="5" />
            <cell x="3" y="3" solution="O" />
        </grid>
        <word id="1"><cells x="1-2" y="1" /></word>
        <word id="2"><cells x="1-3" y="2" /></word>
        <word id="3"><cells x="2-3" y="3" /></word>
        <word id="4"><cells x="1" y="1-2" /></word>
        <word id="5"><cells x="2" y="1-3" /></word>
        <word id="6"><cells x="3" y="2-3" /></word>
        <clues>
            <title><b>Across</b></title>
            <clue word="1" number="1">California</clue>
            <clue word="2" number="3">Robot</clue>
            <clue word="3" number="5">Hello</clue>
        </clues>
        <clues>
            <title><b>Down</b></title>
            <clue word="4" number="1">Cowboy</clue>
            <clue word="5" number="2">All</clue>
            <clue word="6" number="4">Also</clue>
        </clues>
    </crossword>
</rectangular-puzzle>
XML;

    $file = UploadedFile::fake()->createWithContent('puzzle.jpz', $xml);

    Livewire::test('pages::crosswords.index')
        ->set('importFile', $file)
        ->call('importPuzzle')
        ->assertRedirect();

    expect($user->crosswords()->count())->toBe(1);

    $crossword = $user->crosswords()->first();
    expect($crossword->title)->toBe('Test JPZ')
        ->and($crossword->author)->toBe('JPZ Author')
        ->and($crossword->width)->toBe(3)
        ->and($crossword->height)->toBe(3)
        ->and($crossword->solution[0][0])->toBe('C');
});

test('users can import a valid pdf file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $pdfContents = file_get_contents(base_path('packages/crossword-io/tests/fixtures/march.pdf'));
    $file = UploadedFile::fake()->createWithContent('puzzle.pdf', $pdfContents);

    Livewire::test('pages::crosswords.index')
        ->set('importFile', $file)
        ->call('importPuzzle')
        ->assertRedirect();

    expect($user->crosswords()->count())->toBe(1);

    $crossword = $user->crosswords()->first();
    expect($crossword->title)->toBe('MARCH')
        ->and($crossword->author)->toBe('Jimmy and Evelyn Johnson')
        ->and($crossword->width)->toBe(15)
        ->and($crossword->height)->toBe(15)
        ->and($crossword->solution[0][0])->toBe('A')
        ->and($crossword->solution[0][4])->toBe('#')
        ->and($crossword->clues_across)->not->toBeEmpty()
        ->and($crossword->clues_down)->not->toBeEmpty();
});

test('invalid ipuz file shows error', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $file = UploadedFile::fake()->createWithContent('bad.ipuz', 'not valid json!!!');

    Livewire::test('pages::crosswords.index')
        ->set('importFile', $file)
        ->call('importPuzzle')
        ->assertSet('importError', fn ($val) => str_contains($val, 'Invalid JSON'));

    expect($user->crosswords()->count())->toBe(0);
});
