<?php

use App\Exceptions\PuzImportException;
use App\Services\PuzImporter;

beforeEach(function () {
    $this->importer = app(PuzImporter::class);
});

/**
 * Build a minimal valid .puz binary for testing.
 */
function buildPuz(int $width, int $height, string $solutionBoard, array $clueStrings, string $title = '', string $author = '', string $copyright = '', string $notes = '', ?string $gextData = null): string
{
    $numClues = count($clueStrings);

    // Player state: '.' for blocks, '-' for empty
    $playerState = str_replace(range('A', 'Z'), '-', $solutionBoard);

    // String table: title, author, copyright, clues..., notes (all null-terminated)
    $stringTable = $title."\0".$author."\0".$copyright."\0";
    foreach ($clueStrings as $clue) {
        $stringTable .= $clue."\0";
    }
    $stringTable .= $notes."\0";

    // CIB: 8 bytes at offset 0x2C
    $cib = pack('CCvvv', $width, $height, $numClues, 0x0001, 0);

    // Compute CIB checksum
    $cibCksum = puzCksum($cib);

    // Compute overall checksum
    $cksum = $cibCksum;
    $cksum = puzCksum($solutionBoard, $cksum);
    $cksum = puzCksum($playerState, $cksum);

    // For string checksums, only include non-empty strings with their null terminators
    $stringsForCksum = '';
    foreach ([$title, $author, $copyright] as $s) {
        if ($s !== '') {
            $stringsForCksum .= $s."\0";
        }
    }
    foreach ($clueStrings as $s) {
        $stringsForCksum .= $s."\0";
    }
    if ($notes !== '') {
        $stringsForCksum .= $notes."\0";
    }
    $cksum = puzCksum($stringsForCksum, $cksum);

    // Build header (52 bytes)
    $header = pack('v', $cksum);                      // 0x00: overall checksum
    $header .= "ACROSS&DOWN\0";                       // 0x02: magic (12 bytes)
    $header .= pack('v', $cibCksum);                  // 0x0E: CIB checksum
    $header .= str_repeat("\0", 8);                   // 0x10-0x17: masked checksums (skip for simplicity)
    $header .= "1.3\0";                               // 0x18: version
    $header .= "\0\0";                                // 0x1C: reserved
    $header .= "\0\0";                                // 0x1E: scrambled checksum
    $header .= str_repeat("\0", 12);                  // 0x20: reserved
    $header .= $cib;                                  // 0x2C: width, height, numClues, bitmask, scrambled tag

    $binary = $header.$solutionBoard.$playerState.$stringTable;

    // Add GEXT extension section if provided
    if ($gextData !== null) {
        $gextCksum = puzCksum($gextData);
        $binary .= 'GEXT';
        $binary .= pack('v', strlen($gextData));
        $binary .= pack('v', $gextCksum);
        $binary .= $gextData;
        $binary .= "\0";
    }

    return $binary;
}

/**
 * CRC-16 variant used by .puz files.
 */
function puzCksum(string $data, int $cksum = 0): int
{
    for ($i = 0; $i < strlen($data); $i++) {
        if ($cksum & 0x0001) {
            $cksum = ($cksum >> 1) + 0x8000;
        } else {
            $cksum = $cksum >> 1;
        }
        $cksum = ($cksum + ord($data[$i])) & 0xFFFF;
    }

    return $cksum;
}

it('imports a valid puz file', function () {
    // 3x3 grid:
    // C A .
    // B O T
    // . L O
    // 3x3 grid:
    // C A .
    // B O T
    // . L O
    // Slots: 1A(CA), 1D(CB), 2D(AOL), 3A(BOT), 4D(TO), 5A(LO)
    // .puz clue order: 1A, 1D, 2D, 3A, 4D, 5A
    $solution = 'CA.BOT.LO';
    $clues = ['California', 'Cowboy', 'All', 'Robot helper', 'Also', 'Hello'];

    $binary = buildPuz(3, 3, $solution, $clues, 'Test Puzzle', 'Author');

    $result = $this->importer->import($binary);

    expect($result['title'])->toBe('Test Puzzle')
        ->and($result['author'])->toBe('Author')
        ->and($result['width'])->toBe(3)
        ->and($result['height'])->toBe(3)
        ->and($result['solution'])->toBe([
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ])
        ->and($result['clues_across'])->toHaveCount(3)
        ->and($result['clues_down'])->toHaveCount(3)
        ->and($result['clues_across'][0]['clue'])->toBe('California')
        ->and($result['clues_down'][0]['clue'])->toBe('Cowboy');
});

it('throws on content shorter than 52 bytes', function () {
    $this->importer->import('too short');
})->throws(PuzImportException::class, 'too short');

it('throws on invalid magic string', function () {
    $binary = str_repeat("\0", 52);
    $this->importer->import($binary);
})->throws(PuzImportException::class, 'ACROSS&DOWN');

it('throws on scrambled puzzles', function () {
    // Build valid header but set scrambled tag to non-zero
    $header = pack('v', 0);                           // overall checksum
    $header .= "ACROSS&DOWN\0";                       // magic
    $header .= pack('v', 0);                          // CIB checksum
    $header .= str_repeat("\0", 8);                   // masked checksums
    $header .= "1.3\0";                               // version
    $header .= "\0\0";                                // reserved
    $header .= "\0\0";                                // scrambled checksum
    $header .= str_repeat("\0", 12);                  // reserved
    $header .= pack('CCvvv', 3, 3, 0, 0, 1);         // CIB with scrambled=1

    // Pad with enough data
    $header .= str_repeat("\0", 100);

    $this->importer->import($header);
})->throws(PuzImportException::class, 'Scrambled');

it('orders clues correctly when across and down share a number', function () {
    // 3x3 grid, no blocks:
    // A B C
    // D E F
    // G H I
    // 1-Across (ABC), 1-Down (ADG), 2-Down (BEH), 3-Down (CFI), 4-Across (DEF), 7-Across (GHI)
    // .puz order: 1A, 1D, 2D, 3D, 4A, 7A (but numbering depends on GridNumberer)
    $solution = 'ABCDEFGHI';
    // Grid numbering: 1(A,D), 2(D), 3(D), 4(A), 7(A) — but exact numbers from GridNumberer
    // With no blocks, slots are: Across: 1(row0), 4(row1), 7(row2); Down: 1(col0), 2(col1), 3(col2)
    // .puz order: 1-across, 1-down, 2-down, 3-down, 4-across, 7-across
    $clues = ['Row one', 'Column one', 'Column two', 'Column three', 'Row two', 'Row three'];

    $binary = buildPuz(3, 3, $solution, $clues, 'No Blocks');

    $result = $this->importer->import($binary);

    expect($result['clues_across'])->toHaveCount(3)
        ->and($result['clues_down'])->toHaveCount(3)
        ->and($result['clues_across'][0]['clue'])->toBe('Row one')
        ->and($result['clues_across'][0]['number'])->toBe(1)
        ->and($result['clues_down'][0]['clue'])->toBe('Column one')
        ->and($result['clues_down'][0]['number'])->toBe(1)
        ->and($result['clues_across'][1]['clue'])->toBe('Row two')
        ->and($result['clues_down'][1]['clue'])->toBe('Column two');
});

it('handles GEXT circles in styles', function () {
    // 3x3 grid with circle on cell (0,0) and (1,2)
    $solution = 'CA.BOT.LO';
    $clues = ['CA', 'BOT', 'LO', 'CB', 'AOL', 'TO'];

    // GEXT data: 9 bytes, 0x80 at positions 0 and 5
    $gext = "\x80\x00\x00\x00\x00\x80\x00\x00\x00";

    $binary = buildPuz(3, 3, $solution, $clues, 'Circles', '', '', '', $gext);

    $result = $this->importer->import($binary);

    expect($result['styles'])->not->toBeNull()
        ->and($result['styles']['0,0'])->toBe(['shapebg' => 'circle'])
        ->and($result['styles']['1,2'])->toBe(['shapebg' => 'circle'])
        ->and($result['styles'])->toHaveCount(2);
});

it('converts ISO-8859-1 strings to UTF-8', function () {
    $solution = 'AB.CD.EF.';
    $clues = ['Caf'.chr(0xE9), 'Na'.chr(0xEF).'ve']; // café, naïve in ISO-8859-1

    $binary = buildPuz(3, 3, $solution, $clues, 'R'.chr(0xE9).'sum'.chr(0xE9));

    $result = $this->importer->import($binary);

    expect($result['title'])->toBe('Résumé')
        ->and($result['clues_across'][0]['clue'])->toBe('Café');
});

it('returns null for empty optional fields', function () {
    $solution = 'CA.BOT.LO';
    $clues = ['CA', 'BOT', 'LO', 'CB', 'AOL', 'TO'];

    $binary = buildPuz(3, 3, $solution, $clues);

    $result = $this->importer->import($binary);

    expect($result['title'])->toBeNull()
        ->and($result['author'])->toBeNull()
        ->and($result['copyright'])->toBeNull()
        ->and($result['notes'])->toBeNull()
        ->and($result['styles'])->toBeNull()
        ->and($result['metadata'])->toBeNull();
});
