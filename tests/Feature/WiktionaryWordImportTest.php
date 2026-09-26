<?php

use App\Console\Commands\GenerateWordList;
use App\Models\Word;
use App\Services\WiktionaryWordImporter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * @param  list<string>  $titles
 * @return array<string, mixed>
 */
function wiktionaryPage(array $titles, ?string $continue = null): array
{
    return array_filter([
        'batchcomplete' => true,
        'continue' => $continue === null ? null : ['cmcontinue' => $continue, 'continue' => '-||'],
        'query' => ['categorymembers' => array_map(fn (string $title): array => ['ns' => 0, 'title' => $title], $titles)],
    ], fn (mixed $value): bool => $value !== null);
}

test('titles are normalized to grid answers', function (string $title, ?string $expected) {
    expect(WiktionaryWordImporter::normalize($title))->toBe($expected);
})->with([
    'plain word' => ['ocean', 'OCEAN'],
    'phrase' => ['hot dog', 'HOTDOG'],
    'hyphenated' => ['hot-air balloon', 'HOTAIRBALLOON'],
    'apostrophe' => ["rock 'n' roll", 'ROCKNROLL'],
    'accents folded' => ['café', 'CAFE'],
    'proper noun' => ['Paris', null],
    'acronym' => ['NASA', null],
    'suffix' => ['-ness', null],
    'prefix' => ['anti-', null],
    'digits' => ['catch-22', null],
    'symbols' => ['!Kung', null],
    'too short' => ['ox', null],
    'too long' => ['antidisestablishmentarianism', null],
    'phrase of four words' => ['hot as a pistol', null],
]);

test('it adds only new usable words, scored below vetted words', function () {
    Word::factory()->word('OCEAN')->create(['score' => 80]);
    Http::fake(['en.wiktionary.org/*' => Http::response(wiktionaryPage(['ocean', 'hot dog', 'Paris', 'NASA', 'zebra', 'hot-dog']))]);

    $result = app(WiktionaryWordImporter::class)->import(pages: 1);

    expect($result)->toBe(['pages' => 1, 'scanned' => 6, 'added' => 2, 'finished' => true])
        ->and(Word::query()->orderBy('word')->pluck('word')->all())->toBe(['HOTDOG', 'OCEAN', 'ZEBRA'])
        ->and(Word::where('word', 'OCEAN')->value('score'))->toEqual(80)
        ->and(Word::where('word', 'ZEBRA')->first())
        ->length->toBe(5)
        ->score->toEqual(round(GenerateWordList::calculateScore('ZEBRA') - WiktionaryWordImporter::SCORE_PENALTY, 2));
});

test('each run continues from where the last one stopped', function () {
    Http::fake(function (Request $request) {
        return match ($request['cmcontinue'] ?? null) {
            null => Http::response(wiktionaryPage(['apple'], 'page-2')),
            'page-2' => Http::response(wiktionaryPage(['banana'], 'page-3')),
            'page-3' => Http::response(wiktionaryPage(['cherry'])),
        };
    });

    $importer = app(WiktionaryWordImporter::class);

    expect($importer->import(pages: 1))->toMatchArray(['added' => 1, 'finished' => false])
        ->and($importer->import(pages: 5))->toMatchArray(['pages' => 2, 'added' => 2, 'finished' => true])
        ->and(Word::query()->orderBy('word')->pluck('word')->all())->toBe(['APPLE', 'BANANA', 'CHERRY']);

    $importer->import(pages: 1);

    Http::assertSent(fn (Request $request): bool => ! isset($request['cmcontinue']) && $request['cmtitle'] === 'Category:English lemmas');
    Http::assertSentCount(4);
});

test('the command reports progress and can restart from the beginning', function () {
    Http::fake(function (Request $request) {
        return isset($request['cmcontinue'])
            ? Http::response(wiktionaryPage(['banana']))
            : Http::response(wiktionaryPage(['apple', 'Paris'], 'page-2'));
    });

    $this->artisan('words:import-wiktionary --pages=1')
        ->expectsOutputToContain('Read 1 page(s), 2 entries: added 1 new word(s).')
        ->assertSuccessful();

    $this->artisan('words:import-wiktionary --pages=1 --restart')
        ->expectsOutputToContain('Read 1 page(s), 2 entries: added 0 new word(s).')
        ->assertSuccessful();

    $this->artisan('words:import-wiktionary --pages=5')
        ->expectsOutputToContain('Read 1 page(s), 1 entries: added 1 new word(s).')
        ->expectsOutputToContain('Reached the end of "English lemmas"')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_contains($request->header('User-Agent')[0] ?? '', config('app.name')));
});

test('the command fails cleanly when Wiktionary is unreachable', function () {
    Sleep::fake();
    Http::fake(['en.wiktionary.org/*' => Http::response('Service Unavailable', 503)]);

    $this->artisan('words:import-wiktionary --pages=1')
        ->expectsOutputToContain('Could not read Wiktionary')
        ->assertFailed();

    expect(Word::count())->toBe(0);
});
