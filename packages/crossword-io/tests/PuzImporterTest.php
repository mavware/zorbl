<?php

use Zorbl\CrosswordIO\Exceptions\PuzImportException;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\PuzImporter;

beforeEach(function () {
    $this->importer = new PuzImporter(new GridNumberer);
});

/**
 * Build a minimal valid .puz binary for testing.
 */
function buildPuz(int $width, int $height, string $solutionBoard, array $clueStrings, string $title = '', string $author = '', string $copyright = '', string $notes = '', ?string $gextData = null): string
{
    $numClues = count($clueStrings);
    $playerState = str_replace(range('A', 'Z'), '-', $solutionBoard);

    $stringTable = $title."\0".$author."\0".$copyright."\0";
    foreach ($clueStrings as $clue) {
        $stringTable .= $clue."\0";
    }
    $stringTable .= $notes."\0";

    $cib = pack('CCvvv', $width, $height, $numClues, 0x0001, 0);
    $cibCksum = puzCksum($cib);

    $cksum = $cibCksum;
    $cksum = puzCksum($solutionBoard, $cksum);
    $cksum = puzCksum($playerState, $cksum);

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

    $header = pack('v', $cksum);
    $header .= "ACROSS&DOWN\0";
    $header .= pack('v', $cibCksum);
    $header .= str_repeat("\0", 8);
    $header .= "1.3\0";
    $header .= "\0\0";
    $header .= "\0\0";
    $header .= str_repeat("\0", 12);
    $header .= $cib;

    $binary = $header.$solutionBoard.$playerState.$stringTable;

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
    $header = pack('v', 0);
    $header .= "ACROSS&DOWN\0";
    $header .= pack('v', 0);
    $header .= str_repeat("\0", 8);
    $header .= "1.3\0";
    $header .= "\0\0";
    $header .= "\0\0";
    $header .= str_repeat("\0", 12);
    $header .= pack('CCvvv', 3, 3, 0, 0, 1);
    $header .= str_repeat("\0", 100);

    $this->importer->import($header);
})->throws(PuzImportException::class, 'Scrambled');

it('orders clues correctly when across and down share a number', function () {
    $solution = 'ABCDEFGHI';
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
    $solution = 'CA.BOT.LO';
    $clues = ['CA', 'BOT', 'LO', 'CB', 'AOL', 'TO'];
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
    $clues = ['Caf'.chr(0xE9), 'Na'.chr(0xEF).'ve'];

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
