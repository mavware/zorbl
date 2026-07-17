<?php

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function validIpuzContent(): string
{
    return json_encode([
        'version' => 'http://ipuz.org/v2',
        'kind' => ['http://ipuz.org/crossword#1'],
        'dimensions' => ['width' => 3, 'height' => 3],
        'title' => 'Test Puzzle',
        'author' => 'Test Author',
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
            'Across' => [[1, 'California'], [3, 'Robot'], [5, 'Hello']],
            'Down' => [[1, 'Cowboy'], [2, 'All'], [4, 'Also']],
        ],
    ]);
}

test('converter page is publicly accessible', function () {
    $this->get(route('tools.convert'))->assertOk();
});

test('converter page loads without authentication', function () {
    Livewire::test('pages::tools.convert')
        ->assertOk()
        ->assertSee('Crossword File Converter');
});

test('converter page carries SEO content and structured data', function () {
    $html = $this->get(route('tools.convert'))->getContent();

    expect($html)
        // Canonical + meta description via <x-seo-meta>.
        ->toContain('<link rel="canonical" href="'.route('tools.convert').'"')
        ->toContain('<meta name="description"')
        // Structured data: tool + FAQ + breadcrumb.
        ->toContain('"@type":"SoftwareApplication"')
        ->toContain('"@type":"FAQPage"')
        ->toContain('"@type":"BreadcrumbList"')
        // Crawlable content sections that target real search queries.
        ->toContain('How to convert a crossword file')
        ->toContain('.puz to .ipuz')
        ->toContain('Frequently asked questions');

    // Every ld+json block must be valid JSON.
    preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $block) {
        json_decode($block);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    }
});

test('converter FAQ content matches the FAQ structured data', function () {
    $html = $this->get(route('tools.convert'))->getContent();

    preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
    $faqBlock = collect($m[1])
        ->map(fn ($b) => json_decode($b, true))
        ->firstWhere('@type', 'FAQPage');

    expect($faqBlock)->not->toBeNull();

    // Each schema question must also appear as visible on-page text.
    foreach ($faqBlock['mainEntity'] as $question) {
        expect($html)->toContain($question['name']);
    }
});

test('uploading a valid ipuz file shows puzzle info', function () {
    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', validIpuzContent());

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('fileLoaded', true)
        ->assertSet('puzzleTitle', 'Test Puzzle')
        ->assertSet('puzzleWidth', 3)
        ->assertSet('puzzleHeight', 3)
        ->assertSet('clueCount', 6)
        ->assertSet('error', '');
});

test('uploading an invalid file shows error', function () {
    $file = UploadedFile::fake()->createWithContent('bad.ipuz', 'not valid json!!!');

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('fileLoaded', false)
        ->assertSet('error', fn ($val) => $val !== '');
});

test('converting ipuz to puz returns a download', function () {
    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', validIpuzContent());

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('fileLoaded', true)
        ->set('targetFormat', 'puz')
        ->call('convert')
        ->assertFileDownloaded('test-puzzle.puz');
});

test('converting ipuz to jpz returns a download', function () {
    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', validIpuzContent());

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('fileLoaded', true)
        ->set('targetFormat', 'jpz')
        ->call('convert')
        ->assertFileDownloaded('test-puzzle.jpz');
});

test('converting ipuz to ipuz returns a download', function () {
    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', validIpuzContent());

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('fileLoaded', true)
        ->set('targetFormat', 'ipuz')
        ->call('convert')
        ->assertFileDownloaded('test-puzzle.ipuz');
});

test('resetting converter clears all state', function () {
    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', validIpuzContent());

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('fileLoaded', true)
        ->call('resetConverter')
        ->assertSet('fileLoaded', false)
        ->assertSet('puzzleTitle', '')
        ->assertSet('error', '');
});

test('target format defaults to puz when uploading ipuz', function () {
    $file = UploadedFile::fake()->createWithContent('puzzle.ipuz', validIpuzContent());

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('targetFormat', 'puz');
});

test('target format defaults to ipuz when uploading puz', function () {
    $solutionBoard = 'CA.BOT.LO';
    $playerState = '--.---.--';
    $clues = ['California', 'Cowboy', 'All', 'Robot', 'Also', 'Hello'];

    $numClues = count($clues);
    $cib = pack('CCvvv', 3, 3, $numClues, 0x0001, 0);

    $header = pack('v', 0);
    $header .= "ACROSS&DOWN\0";
    $header .= pack('v', 0);
    $header .= str_repeat("\0", 8);
    $header .= "1.3\0";
    $header .= "\0\0";
    $header .= "\0\0";
    $header .= str_repeat("\0", 12);
    $header .= $cib;

    $stringTable = "Test Puz\0Author\0\0";
    foreach ($clues as $clue) {
        $stringTable .= $clue."\0";
    }
    $stringTable .= "\0";

    $binary = $header.$solutionBoard.$playerState.$stringTable;
    $file = UploadedFile::fake()->createWithContent('puzzle.puz', $binary);

    Livewire::test('pages::tools.convert')
        ->set('file', $file)
        ->assertSet('targetFormat', 'ipuz');
});

test('convert without file shows error', function () {
    Livewire::test('pages::tools.convert')
        ->call('convert')
        ->assertSet('error', fn ($val) => str_contains($val, 'upload'));
});
