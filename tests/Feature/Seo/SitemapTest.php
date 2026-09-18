<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('sitemap.xml');
});

test('sitemap.xml is served with xml content type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    expect($response->getContent())->toStartWith('<?xml version="1.0"');
});

test('sitemap lists core public pages', function () {
    $xml = $this->get('/sitemap.xml')->getContent();

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

test('sitemap lists the constructors directory and public profiles', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();

    // A user with only a draft is not a public constructor.
    $draftOnly = User::factory()->create();
    Crossword::factory()->for($draftOnly)->create(['is_published' => false]);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)
        ->toContain(route('constructors.index'))
        ->toContain(route('constructors.show', $constructor->id))
        // Constructors with no published puzzles are excluded.
        ->not->toContain(route('constructors.show', $draftOnly->id));
});

test('sitemap lists published puzzles and excludes drafts', function () {
    $published = Crossword::factory()->published()->create();
    $draft = Crossword::factory()->create(['is_published' => false]);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)
        ->toContain(route('puzzles.solve', $published->id))
        ->not->toContain(route('puzzles.solve', $draft->id));
});

test('sitemap lists word pages that have approved clues and excludes the rest', function () {
    $clued = Word::factory()->word('APPLE')->create();
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    // Only a pending submission: the page is noindex, so keep it out.
    $pendingOnly = Word::factory()->word('PEAR')->create();
    ClueEntry::factory()->standalone()->create(['answer' => 'PEAR', 'status' => ClueEntry::STATUS_PENDING]);

    $bare = Word::factory()->word('PLUM')->create();

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)
        ->toContain(route('words.show', $clued))
        ->not->toContain(route('words.show', $pendingOnly))
        ->not->toContain(route('words.show', $bare));
});

test('sitemap word entries use the latest approved clue as lastmod', function () {
    Word::factory()->word('APPLE')->create();

    $this->travelTo('2026-01-05 12:00:00');
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    $this->travelTo('2026-03-20 12:00:00');
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    // A newer pending clue must not bump lastmod: it isn't visible on the page.
    $this->travelTo('2026-06-01 12:00:00');
    ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_PENDING]);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain(
        '<loc>'.htmlspecialchars(route('words.show', 'APPLE'), ENT_XML1).'</loc><lastmod>2026-03-20</lastmod>'
    );
});

test('approving a clue invalidates the sitemap cache', function () {
    $word = Word::factory()->word('APPLE')->create();
    $clue = ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_PENDING]);

    $without = $this->get('/sitemap.xml')->getContent();
    expect($without)->not->toContain(route('words.show', $word));
    expect(Cache::has('sitemap.xml'))->toBeTrue();

    // Editing a pending clue shouldn't touch the cache.
    $clue->update(['clue' => 'Fruit with a core']);
    expect(Cache::has('sitemap.xml'))->toBeTrue();

    $clue->update(['status' => ClueEntry::STATUS_APPROVED]);
    expect(Cache::has('sitemap.xml'))->toBeFalse();

    expect($this->get('/sitemap.xml')->getContent())->toContain(route('words.show', $word));
});

test('deleting the last approved clue for a word invalidates the sitemap cache', function () {
    $word = Word::factory()->word('APPLE')->create();
    $clue = ClueEntry::factory()->standalone()->create(['answer' => 'APPLE', 'status' => ClueEntry::STATUS_APPROVED]);

    expect($this->get('/sitemap.xml')->getContent())->toContain(route('words.show', $word));

    $clue->delete();
    expect(Cache::has('sitemap.xml'))->toBeFalse();

    expect($this->get('/sitemap.xml')->getContent())->not->toContain(route('words.show', $word));
});

test('sitemap is well-formed xml that parses cleanly', function () {
    Crossword::factory()->published()->count(3)->create();

    $xml = $this->get('/sitemap.xml')->getContent();

    libxml_use_internal_errors(true);
    $document = simplexml_load_string($xml);

    expect($document)->not->toBeFalse();
    expect((array) $document->url)->not->toBeEmpty();
});

test('publishing a puzzle invalidates the sitemap cache', function () {
    $cached = $this->get('/sitemap.xml')->getContent();
    expect(Cache::has('sitemap.xml'))->toBeTrue();

    $crossword = Crossword::factory()->create(['is_published' => false]);

    // Drafts shouldn't touch the cache.
    expect(Cache::has('sitemap.xml'))->toBeTrue();

    $crossword->update(['is_published' => true]);

    expect(Cache::has('sitemap.xml'))->toBeFalse();

    $fresh = $this->get('/sitemap.xml')->getContent();
    expect($fresh)
        ->toContain(route('puzzles.solve', $crossword->id))
        ->and($fresh)->not->toBe($cached);
});

test('unpublishing a puzzle invalidates the sitemap cache', function () {
    $crossword = Crossword::factory()->published()->create();
    $withPuzzle = $this->get('/sitemap.xml')->getContent();
    expect($withPuzzle)->toContain(route('puzzles.solve', $crossword->id));

    $crossword->update(['is_published' => false]);
    expect(Cache::has('sitemap.xml'))->toBeFalse();

    $withoutPuzzle = $this->get('/sitemap.xml')->getContent();
    expect($withoutPuzzle)->not->toContain(route('puzzles.solve', $crossword->id));
});

test('editing a draft does not invalidate the sitemap cache', function () {
    $crossword = Crossword::factory()->create(['is_published' => false]);

    $this->get('/sitemap.xml');
    expect(Cache::has('sitemap.xml'))->toBeTrue();

    $crossword->update(['title' => 'Renamed draft']);

    expect(Cache::has('sitemap.xml'))->toBeTrue();
});

test('robots.txt references the sitemap', function () {
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
        // Public paths must remain crawlable (never disallowed).
        ->not->toContain('Disallow: /puzzles')
        ->not->toContain('Disallow: /help')
        ->not->toContain('Disallow: /tools')
        ->not->toContain('Disallow: /constructors');
});
