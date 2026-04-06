<?php

use Zorbl\CrosswordIO\Exceptions\PdfImportException;
use Zorbl\CrosswordIO\GridNumberer;
use Zorbl\CrosswordIO\Importers\PdfImporter;

beforeEach(function () {
    $this->importer = new PdfImporter(new GridNumberer);
});

it('throws on non-PDF content', function () {
    $this->importer->import('This is not a PDF file');
})->throws(PdfImportException::class, '%PDF-');

it('throws on empty content', function () {
    $this->importer->import('');
})->throws(PdfImportException::class, '%PDF-');

it('imports the march crossword PDF', function () {
    $contents = file_get_contents(__DIR__.'/fixtures/march.pdf');
    $result = $this->importer->import($contents);

    expect($result['width'])->toBe(15)
        ->and($result['height'])->toBe(15)
        ->and($result['title'])->toBe('MARCH')
        ->and($result['author'])->toBe('Jimmy and Evelyn Johnson');

    // Check solution grid
    expect($result['solution'])->toHaveCount(15);
    expect($result['solution'][0])->toHaveCount(15);

    // First row: ASSN#ASIA##PEAS
    expect($result['solution'][0][0])->toBe('A')
        ->and($result['solution'][0][1])->toBe('S')
        ->and($result['solution'][0][2])->toBe('S')
        ->and($result['solution'][0][3])->toBe('N')
        ->and($result['solution'][0][4])->toBe('#')
        ->and($result['solution'][0][5])->toBe('A');

    // Check grid numbering
    expect($result['grid'][0][0])->toBe(1)  // 1-Across starts here
        ->and($result['grid'][0][4])->toBe('#');  // Black square

    // Check clues
    expect($result['clues_across'])->not->toBeEmpty()
        ->and($result['clues_down'])->not->toBeEmpty();

    // First across clue: "Association (abbr.)"
    expect($result['clues_across'][0]['number'])->toBe(1)
        ->and($result['clues_across'][0]['clue'])->toBe('Association (abbr.)');

    // First down clue: "Appall"
    expect($result['clues_down'][0]['number'])->toBe(1)
        ->and($result['clues_down'][0]['clue'])->toBe('Appall');

    // Check multi-line clue was properly joined: "55 Cooked until chewy (2 wds.)"
    $clue55 = collect($result['clues_across'])->firstWhere('number', 55);
    expect($clue55)->not->toBeNull()
        ->and($clue55['clue'])->toBe('Cooked until chewy (2 wds.)');
});
