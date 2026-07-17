<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleComment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Extract and JSON-decode every ld+json block on a rendered page.
 *
 * @return array<int, mixed>
 */
function jsonLdBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $html, $matches);

    return array_map(function (string $raw): mixed {
        $decoded = json_decode($raw, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        return $decoded;
    }, $matches[1]);
}

test('public puzzle pages get a unique, branded title', function () {
    $a = Crossword::factory()->published()->create(['title' => 'Morning Coffee']);
    $b = Crossword::factory()->published()->create(['title' => 'Evening Tea']);

    $titleA = $this->get(route('puzzles.solve', $a))->getContent();
    $titleB = $this->get(route('puzzles.solve', $b))->getContent();

    expect($titleA)->toContain('<title>Morning Coffee Crossword — '.config('app.name').'</title>')
        ->and($titleB)->toContain('<title>Evening Tea Crossword — '.config('app.name').'</title>');
});

test('public layout appends the app name to page titles', function () {
    $body = $this->get(route('puzzles.index'))->getContent();

    expect($body)->toContain('<title>Browse Puzzles — '.config('app.name').'</title>');
});

test('legal pages carry a canonical and meta description', function () {
    $body = $this->get(route('legal.privacy'))->getContent();

    expect($body)
        ->toContain('<link rel="canonical" href="'.route('legal.privacy').'"')
        ->toContain('<meta name="description"')
        ->toContain('<title>Privacy Policy — '.config('app.name').'</title>');
});

test('homepage title stays within the search-result length sweet spot', function () {
    $html = $this->get('/')->getContent();

    expect(preg_match('#<title>(.+?)</title>#s', $html, $m))->toBe(1);

    // Google/X/LinkedIn truncate past ~60 characters.
    expect(mb_strlen(html_entity_decode($m[1])))->toBeLessThanOrEqual(60);
});

test('homepage og:title and twitter:title match the page title', function () {
    $html = $this->get('/')->getContent();

    preg_match('#<title>(.+?)</title>#s', $html, $title);
    $expected = html_entity_decode($title[1]);

    expect($html)
        ->toContain('<meta property="og:title" content="'.$expected.'">')
        ->toContain('<meta name="twitter:title" content="'.$expected.'">');
});

test('constructor profile emits ProfilePage, Person and BreadcrumbList', function () {
    $constructor = User::factory()->create(['name' => 'Grid Master']);
    Crossword::factory()->published()->for($constructor)->create(['title' => 'Master Puzzle']);

    $blocks = collect(jsonLdBlocks($this->get(route('constructors.show', $constructor))->getContent()));

    $profile = $blocks->firstWhere('@type', 'ProfilePage');
    expect($profile)->not->toBeNull()
        ->and($profile['mainEntity']['@type'])->toBe('Person')
        ->and($profile['mainEntity']['name'])->toBe('Grid Master');

    $puzzleNames = collect($profile['about']['itemListElement'])->pluck('name');
    expect($puzzleNames)->toContain('Master Puzzle');

    $breadcrumb = $blocks->firstWhere('@type', 'BreadcrumbList');
    expect(collect($breadcrumb['itemListElement'])->pluck('name'))
        ->toContain('Constructors')
        ->toContain('Grid Master');
});

test('constructors directory emits a CollectionPage ItemList', function () {
    $constructor = User::factory()->create(['name' => 'Directory Person']);
    Crossword::factory()->published()->for($constructor)->create();

    $collection = collect(jsonLdBlocks($this->get(route('constructors.index'))->getContent()))
        ->firstWhere('@type', 'CollectionPage');

    expect($collection)->not->toBeNull();
    expect(collect($collection['mainEntity']['itemListElement'])->pluck('name'))
        ->toContain('Directory Person');
});

test('welcome page includes WebSite schema with a SearchAction', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SearchAction"', false)
        ->assertSee('search_term_string', false);
});

test('welcome page graph includes Organization and site navigation for key sections', function () {
    $html = $this->get('/')->getContent();

    $graph = collect(jsonLdBlocks($html))
        ->firstWhere(fn ($block) => isset($block['@graph']));

    expect($graph)->not->toBeNull();

    $types = collect($graph['@graph'])->pluck('@type');
    expect($types)->toContain('Organization')
        ->toContain('WebSite')
        ->toContain('ItemList');

    $nav = collect($graph['@graph'])->firstWhere('@type', 'ItemList');
    $navNames = collect($nav['itemListElement'])->pluck('name');

    expect($navNames)->toContain('Build a Crossword')
        ->toContain('Puzzle of the Day')
        ->toContain('Newest Puzzles')
        ->toContain('Trending Puzzles');
});

test('browse puzzles page includes a CollectionPage ItemList of newest puzzles', function () {
    Cache::flush();
    $crossword = Crossword::factory()->published()->create(['title' => 'Fresh Grid']);

    $html = $this->get(route('puzzles.index'))->getContent();

    $collection = collect(jsonLdBlocks($html))
        ->firstWhere('@type', 'CollectionPage');

    expect($collection)->not->toBeNull()
        ->and($collection['mainEntity']['@type'])->toBe('ItemList');

    $names = collect($collection['mainEntity']['itemListElement'])->pluck('name');
    expect($names)->toContain('Fresh Grid');
});

test('browse puzzles item list excludes profanity for safe search', function () {
    Cache::flush();
    // The observer recomputes contains_profanity from the title, so use a
    // configured sentinel word rather than setting the flag directly.
    config()->set('profanity.words', ['borfle']);
    Crossword::factory()->published()->create(['title' => 'Clean Puzzle']);
    Crossword::factory()->published()->create(['title' => 'Dirty borfle Puzzle']);

    $html = $this->get(route('puzzles.index'))->getContent();
    $collection = collect(jsonLdBlocks($html))->firstWhere('@type', 'CollectionPage');
    $names = collect($collection['mainEntity']['itemListElement'])->pluck('name');

    expect($names)->toContain('Clean Puzzle')
        ->not->toContain('Dirty borfle Puzzle');
});

test('puzzle of the day history page includes a CollectionPage ItemList', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Daily One']);
    DailyPuzzle::create(['date' => today(), 'crossword_id' => $crossword->id]);

    $html = $this->get(route('puzzles.daily-history'))->getContent();

    $collection = collect(jsonLdBlocks($html))->firstWhere('@type', 'CollectionPage');

    expect($collection)->not->toBeNull()
        ->and($collection['name'])->toBe('Puzzle of the Day')
        ->and($collection['mainEntity']['itemListElement'])->not->toBeEmpty();
});

test('solve page includes a BreadcrumbList', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Crumbs']);

    $breadcrumb = collect(jsonLdBlocks($this->get(route('puzzles.solve', $crossword))->getContent()))
        ->firstWhere('@type', 'BreadcrumbList');

    expect($breadcrumb)->not->toBeNull();
    $names = collect($breadcrumb['itemListElement'])->pluck('name');
    expect($names)->toContain('Browse Puzzles')->toContain('Crumbs');
});

test('solve page adds aggregateRating and play count when data exists', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Rated Puzzle',
        'cached_completed_count' => 42,
    ]);

    PuzzleComment::factory()->for($crossword)->create(['rating' => 4]);
    PuzzleComment::factory()->for($crossword)->create(['rating' => 5]);

    $game = collect(jsonLdBlocks($this->get(route('puzzles.solve', $crossword))->getContent()))
        ->firstWhere('@type', 'Game');

    expect($game['aggregateRating']['@type'])->toBe('AggregateRating')
        ->and($game['aggregateRating']['ratingValue'])->toBe(4.5)
        ->and($game['aggregateRating']['ratingCount'])->toBe(2)
        ->and($game['interactionStatistic']['userInteractionCount'])->toBe(42);
});

test('solve page omits aggregateRating when there are no ratings', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Unrated',
        'cached_completed_count' => 0,
    ]);

    $game = collect(jsonLdBlocks($this->get(route('puzzles.solve', $crossword))->getContent()))
        ->firstWhere('@type', 'Game');

    expect($game)->not->toHaveKey('aggregateRating')
        ->not->toHaveKey('interactionStatistic');
});

test('solve page includes Game schema with author and image', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $crossword = Crossword::factory()->for($owner)->published()->create([
        'title' => 'Indexable Puzzle',
    ]);

    $response = $this->get(route('puzzles.solve', $crossword));

    $response->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"Game"', false)
        ->assertSee('"Indexable Puzzle"', false)
        ->assertSee('"Ada Lovelace"', false)
        ->assertSee(route('puzzles.og', $crossword), false);
});

test('json-ld on solve page parses as valid json', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Parseable',
    ]);

    $html = $this->get(route('puzzles.solve', $crossword))->getContent();

    expect(preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match))->toBe(1);

    $decoded = json_decode($match[1], true);
    expect($decoded)
        ->toHaveKey('@type', 'Game')
        ->toHaveKey('name', 'Parseable')
        ->toHaveKey('genre', 'Crossword puzzle');
});
