<?php

use App\Http\Controllers\SitemapController;
use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    foreach ([SitemapController::CACHE_KEY_INDEX, ...array_values(SitemapController::SECTIONS)] as $key) {
        Cache::forget($key);
    }
});

// --- Sitemap index ---

test('sitemap.xml serves a sitemap index with xml content type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();
    expect($xml)
        ->toStartWith('<?xml version="1.0"')
        ->toContain('<sitemapindex')
        ->toContain(route('sitemap.section', 'pages'))
        ->toContain(route('sitemap.section', 'puzzles'))
        ->toContain(route('sitemap.section', 'constructors'))
        ->toContain(route('sitemap.section', 'words'));
});

test('sitemap index is well-formed xml', function () {
    libxml_use_internal_errors(true);
    $document = simplexml_load_string($this->get('/sitemap.xml')->getContent());
    expect($document)->not->toBeFalse();
});

// --- Pages sitemap ---

test('pages sitemap lists core public pages', function () {
    $xml = $this->get('/sitemaps/pages.xml')->getContent();

    expect($xml)
        ->toContain(route('home'))
        ->toContain(route('puzzles.index'))
        ->toContain(route('puzzles.daily-history'))
        ->toContain(route('words.index'))
        ->toContain(route('clues.index'))
        ->toContain(route('tools.convert'))
        ->toContain(route('help.index'))
        ->toContain(route('legal.terms'))
        ->toContain(route('legal.privacy'))
        ->toContain(route('legal.cookies'))
        ->toContain(route('legal.dmca'));
});

// --- Constructors sitemap ---

test('constructors sitemap lists the directory and public profiles', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();

    $draftOnly = User::factory()->create();
    Crossword::factory()->for($draftOnly)->create(['is_published' => false]);

    $xml = $this->get('/sitemaps/constructors.xml')->getContent();

    expect($xml)
        ->toContain(route('constructors.show', $constructor->id))
        ->not->toContain(route('constructors.show', $draftOnly->id));
});

// --- Puzzles sitemap ---

test('puzzles sitemap lists published puzzles and excludes drafts', function () {
    $published = Crossword::factory()->published()->create();
    $draft = Crossword::factory()->create(['is_published' => false]);

    $xml = $this->get('/sitemaps/puzzles.xml')->getContent();

    expect($xml)
        ->toContain(route('puzzles.solve', $published->id))
        ->not->toContain(route('puzzles.solve', $draft->id));
});

// --- Words sitemap ---

test('words sitemap lists word pages with approved clues and excludes the rest', function () {
    $clued = Word::factory()->word('APPLE')->create();
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    $pendingOnly = Word::factory()->word('PEAR')->create();
    ClueEntry::factory()->standalone()->create(['answer' => 'PEAR', 'status' => ClueEntry::STATUS_PENDING]);

    $bare = Word::factory()->word('PLUM')->create();

    $xml = $this->get('/sitemaps/words.xml')->getContent();

    expect($xml)
        ->toContain(route('words.show', $clued))
        ->not->toContain(route('words.show', $pendingOnly))
        ->not->toContain(route('words.show', $bare));
});

test('words sitemap entries use the latest approved clue as lastmod', function () {
    Word::factory()->word('APPLE')->create();

    $this->travelTo('2026-01-05 12:00:00');
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    $this->travelTo('2026-03-20 12:00:00');
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    $this->travelTo('2026-06-01 12:00:00');
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_PENDING]);

    $xml = $this->get('/sitemaps/words.xml')->getContent();

    expect($xml)->toContain(
        '<loc>'.htmlspecialchars(route('words.show', 'APPLE'), ENT_XML1).'</loc><lastmod>2026-03-20</lastmod>'
    );
});

// --- Cache invalidation ---

test('approving a clue invalidates the words sitemap cache', function () {
    $word = Word::factory()->word('APPLE')->create();
    $clue = ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_PENDING]);

    $without = $this->get('/sitemaps/words.xml')->getContent();
    expect($without)->not->toContain(route('words.show', $word));
    expect(Cache::has(SitemapController::CACHE_KEY_WORDS))->toBeTrue();

    $clue->update(['clue' => 'Fruit with a core']);
    expect(Cache::has(SitemapController::CACHE_KEY_WORDS))->toBeTrue();

    $clue->update(['status' => ClueEntry::STATUS_APPROVED]);
    expect(Cache::has(SitemapController::CACHE_KEY_WORDS))->toBeFalse();
    expect(Cache::has(SitemapController::CACHE_KEY_INDEX))->toBeFalse();

    expect($this->get('/sitemaps/words.xml')->getContent())->toContain(route('words.show', $word));
});

test('deleting the last approved clue invalidates the words sitemap cache', function () {
    $word = Word::factory()->word('APPLE')->create();
    $clue = ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    expect($this->get('/sitemaps/words.xml')->getContent())->toContain(route('words.show', $word));

    $clue->delete();
    expect(Cache::has(SitemapController::CACHE_KEY_WORDS))->toBeFalse();

    expect($this->get('/sitemaps/words.xml')->getContent())->not->toContain(route('words.show', $word));
});

test('child sitemaps are well-formed xml', function () {
    Crossword::factory()->published()->count(3)->create();

    foreach (['pages', 'puzzles', 'constructors', 'words'] as $section) {
        $xml = $this->get("/sitemaps/{$section}.xml")->getContent();

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);

        expect($document)->not->toBeFalse();
    }
});

test('publishing a puzzle invalidates the puzzles sitemap cache', function () {
    $this->get('/sitemaps/puzzles.xml');
    expect(Cache::has(SitemapController::CACHE_KEY_PUZZLES))->toBeTrue();

    $crossword = Crossword::factory()->create(['is_published' => false]);

    expect(Cache::has(SitemapController::CACHE_KEY_PUZZLES))->toBeTrue();

    $crossword->update(['is_published' => true]);

    expect(Cache::has(SitemapController::CACHE_KEY_PUZZLES))->toBeFalse();
    expect(Cache::has(SitemapController::CACHE_KEY_INDEX))->toBeFalse();

    $fresh = $this->get('/sitemaps/puzzles.xml')->getContent();
    expect($fresh)->toContain(route('puzzles.solve', $crossword->id));
});

test('unpublishing a puzzle invalidates the puzzles sitemap cache', function () {
    $crossword = Crossword::factory()->published()->create();
    $withPuzzle = $this->get('/sitemaps/puzzles.xml')->getContent();
    expect($withPuzzle)->toContain(route('puzzles.solve', $crossword->id));

    $crossword->update(['is_published' => false]);
    expect(Cache::has(SitemapController::CACHE_KEY_PUZZLES))->toBeFalse();

    $withoutPuzzle = $this->get('/sitemaps/puzzles.xml')->getContent();
    expect($withoutPuzzle)->not->toContain(route('puzzles.solve', $crossword->id));
});

test('editing a draft does not invalidate any sitemap cache', function () {
    $crossword = Crossword::factory()->create(['is_published' => false]);

    $this->get('/sitemaps/puzzles.xml');
    $this->get('/sitemap.xml');
    expect(Cache::has(SitemapController::CACHE_KEY_PUZZLES))->toBeTrue();
    expect(Cache::has(SitemapController::CACHE_KEY_INDEX))->toBeTrue();

    $crossword->update(['title' => 'Renamed draft']);

    expect(Cache::has(SitemapController::CACHE_KEY_PUZZLES))->toBeTrue();
    expect(Cache::has(SitemapController::CACHE_KEY_INDEX))->toBeTrue();
});

test('invalid section returns 404', function () {
    $this->get('/sitemaps/invalid.xml')->assertNotFound();
});

// --- robots.txt ---

test('robots.txt references the sitemap index', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8');

    expect($response->getContent())->toContain('Sitemap: '.route('sitemap'));
});

test('robots.txt disallows the private app surface but not public pages', function () {
    $body = $this->get('/robots.txt')->getContent();

    expect($body)
        ->toContain('Disallow: /crosswords')
        ->toContain('Disallow: /solving')
        ->toContain('Disallow: /settings')
        ->not->toContain('Disallow: /puzzles')
        ->not->toContain('Disallow: /help')
        ->not->toContain('Disallow: /tools')
        ->not->toContain('Disallow: /constructors');
});
