<?php

use Zorbl\CrosswordIO\Exceptions\JpzImportException;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\JpzImporter;

beforeEach(function () {
    $this->importer = new JpzImporter(new GridNumberer);
});

function buildJpzXml(int $width = 3, int $height = 3, string $cells = '', string $words = '', string $acrossClues = '', string $downClues = '', string $metadata = ''): string
{
    $ns = 'http://crossword.info/xml/rectangular-puzzle';

    if ($cells === '') {
        $cells = <<<'XML'
            <cell x="1" y="1" solution="C" number="1" />
            <cell x="2" y="1" solution="A" number="2" />
            <cell x="3" y="1" type="block" />
            <cell x="1" y="2" solution="B" number="3" />
            <cell x="2" y="2" solution="O" />
            <cell x="3" y="2" solution="T" number="4" />
            <cell x="1" y="3" type="block" />
            <cell x="2" y="3" solution="L" number="5" />
            <cell x="3" y="3" solution="O" />
XML;
    }

    if ($words === '') {
        $words = <<<'XML'
            <word id="1"><cells x="1-2" y="1" /></word>
            <word id="2"><cells x="1-3" y="2" /></word>
            <word id="3"><cells x="2-3" y="3" /></word>
            <word id="4"><cells x="1" y="1-2" /></word>
            <word id="5"><cells x="2" y="1-3" /></word>
            <word id="6"><cells x="3" y="2-3" /></word>
XML;
    }

    if ($acrossClues === '') {
        $acrossClues = <<<'XML'
            <clue word="1" number="1">California</clue>
            <clue word="2" number="3">Robot helper</clue>
            <clue word="3" number="5">Hello</clue>
XML;
    }

    if ($downClues === '') {
        $downClues = <<<'XML'
            <clue word="4" number="1">Cowboy</clue>
            <clue word="5" number="2">All</clue>
            <clue word="6" number="4">Also</clue>
XML;
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rectangular-puzzle xmlns="{$ns}">
    {$metadata}
    <crossword>
        <grid width="{$width}" height="{$height}">
            <grid-look numbering-scheme="normal" cell-size-in-pixels="21" />
            {$cells}
        </grid>
        {$words}
        <clues>
            <title><b>Across</b></title>
            {$acrossClues}
        </clues>
        <clues>
            <title><b>Down</b></title>
            {$downClues}
        </clues>
    </crossword>
</rectangular-puzzle>
XML;
}

it('imports a valid uncompressed jpz XML', function () {
    $xml = buildJpzXml(metadata: '<metadata><title>Test JPZ</title><creator>Author</creator></metadata>');

    $result = $this->importer->import($xml);

    expect($result['title'])->toBe('Test JPZ')
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

it('imports a gzip-compressed jpz file', function () {
    $xml = buildJpzXml(metadata: '<metadata><title>Compressed</title></metadata>');
    $compressed = gzencode($xml);

    $result = $this->importer->import($compressed);

    expect($result['title'])->toBe('Compressed')
        ->and($result['width'])->toBe(3)
        ->and($result['height'])->toBe(3);
});

it('handles void cells', function () {
    $cells = <<<'XML'
        <cell x="1" y="1" type="void" />
        <cell x="2" y="1" solution="A" number="1" />
        <cell x="3" y="1" solution="B" number="2" />
        <cell x="1" y="2" solution="C" number="3" />
        <cell x="2" y="2" solution="D" />
        <cell x="3" y="2" solution="E" />
        <cell x="1" y="3" solution="F" />
        <cell x="2" y="3" solution="G" />
        <cell x="3" y="3" type="void" />
XML;

    $words = <<<'XML'
        <word id="1"><cells x="2-3" y="1" /></word>
        <word id="2"><cells x="1-3" y="2" /></word>
        <word id="3"><cells x="1-2" y="3" /></word>
        <word id="4"><cells x="2" y="1-2" /></word>
        <word id="5"><cells x="3" y="1-2" /></word>
        <word id="6"><cells x="1" y="2-3" /></word>
XML;

    $across = <<<'XML'
        <clue word="1" number="1">AB</clue>
        <clue word="2" number="3">CDE</clue>
        <clue word="3" number="4">FG</clue>
XML;

    $down = <<<'XML'
        <clue word="4" number="1">AD</clue>
        <clue word="5" number="2">BE</clue>
        <clue word="6" number="3">CF</clue>
XML;

    $xml = buildJpzXml(cells: $cells, words: $words, acrossClues: $across, downClues: $down);
    $result = $this->importer->import($xml);

    expect($result['solution'][0][0])->toBeNull()
        ->and($result['solution'][2][2])->toBeNull();
});

it('handles circle annotations', function () {
    $cells = <<<'XML'
        <cell x="1" y="1" solution="C" number="1" background-shape="circle" />
        <cell x="2" y="1" solution="A" number="2" />
        <cell x="3" y="1" type="block" />
        <cell x="1" y="2" solution="B" number="3" />
        <cell x="2" y="2" solution="O" />
        <cell x="3" y="2" solution="T" number="4" background-shape="circle" />
        <cell x="1" y="3" type="block" />
        <cell x="2" y="3" solution="L" number="5" />
        <cell x="3" y="3" solution="O" />
XML;

    $xml = buildJpzXml(cells: $cells);
    $result = $this->importer->import($xml);

    expect($result['styles'])->not->toBeNull()
        ->and($result['styles']['0,0'])->toBe(['shapebg' => 'circle'])
        ->and($result['styles']['1,2'])->toBe(['shapebg' => 'circle'])
        ->and($result['styles'])->toHaveCount(2);
});

it('throws on invalid XML', function () {
    $this->importer->import('not xml at all <><>');
})->throws(JpzImportException::class, 'Invalid XML');

it('throws when crossword element is missing', function () {
    $xml = <<<'XML'
<?xml version="1.0"?>
<rectangular-puzzle xmlns="http://crossword.info/xml/rectangular-puzzle">
    <metadata><title>No crossword</title></metadata>
</rectangular-puzzle>
XML;

    $this->importer->import($xml);
})->throws(JpzImportException::class, 'Missing crossword');

it('returns null for empty optional fields', function () {
    $xml = buildJpzXml();
    $result = $this->importer->import($xml);

    expect($result['title'])->toBeNull()
        ->and($result['author'])->toBeNull()
        ->and($result['copyright'])->toBeNull()
        ->and($result['notes'])->toBeNull()
        ->and($result['metadata'])->toBeNull();
});
