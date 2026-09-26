<?php

use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
use App\Services\WiktionaryClueWriter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

/**
 * @param  list<string>  $definitions
 * @return array<string, mixed>
 */
function wiktionaryDefinitions(array $definitions): array
{
    return [
        'en' => [[
            'partOfSpeech' => 'Noun',
            'language' => 'English',
            'definitions' => array_map(fn (string $definition): array => ['definition' => $definition], $definitions),
        ]],
        'fr' => [[
            'partOfSpeech' => 'Noun',
            'language' => 'French',
            'definitions' => [['definition' => 'Un mot français']],
        ]],
    ];
}

test('definitions are cleaned into clues', function (string $definition, ?string $expected) {
    expect(WiktionaryClueWriter::toClue($definition))->toBe($expected);
})->with([
    'markup and period' => ['One of the large bodies of <a href="/wiki/water">water</a>.', 'One of the large bodies of water'],
    'leading labels' => ['<span class="usage-label-sense"></span> (transitive) (figurative) To move quickly.', 'To move quickly'],
    'sense-group heading' => ['Terms relating to animals. A mammal of the family Felidae.', 'A mammal of the family Felidae'],
    'space before punctuation' => ['The trap in that game; also <i></i> , the ball.', 'The trap in that game; also, the ball'],
    'entities decoded' => ['Rock &amp; roll.', 'Rock & roll'],
    'lowercase start' => ['a small boat.', 'A small boat'],
    'plural' => ['Plural of ocean.', null],
    'alternative form' => ['Alternative form of hot dog.', null],
    'past tense' => ['Simple past tense of run.', null],
    'empty' => ['<span></span>', null],
    'too long' => [str_repeat('word ', 30), null],
]);

test('wiktionary backfill stores definition clues for the english entry only', function () {
    Word::factory()->word('OCEAN')->create();
    Http::fake(['en.wiktionary.org/api/rest_v1/page/definition/ocean' => Http::response(wiktionaryDefinitions([
        '',
        'One of the large bodies of <a href="/wiki/water">water</a> separating the continents.',
        'Water belonging to an ocean.',
        'An immense expanse.',
    ]))]);

    $this->artisan('clues:backfill --source=wiktionary --approve')
        ->expectsOutputToContain('OCEAN: wrote 2 clue(s).')
        ->assertSuccessful();

    expect(ClueEntry::where('answer', 'OCEAN')->orderBy('id')->pluck('clue')->all())
        ->toBe(['One of the large bodies of water separating the continents', 'An immense expanse'])
        ->and(ClueEntry::where('answer', 'OCEAN')->first()->status)->toBe(ClueEntry::STATUS_APPROVED);

    Http::assertSent(fn (Request $request): bool => str_contains($request->header('User-Agent')[0] ?? '', config('app.name')));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.anthropic.com'));
});

test('a word with no wiktionary entry is reported as failed', function () {
    Word::factory()->word('HOTDOG')->create();
    Http::fake(['en.wiktionary.org/*' => Http::response(['title' => 'Not found.'], 404)]);

    $this->artisan('clues:backfill --source=wiktionary')
        ->expectsOutputToContain('HOTDOG: No Wiktionary entry.')
        ->assertFailed();

    expect(ClueEntry::count())->toBe(0);
});

test('an entry with only inflection definitions is reported as failed', function () {
    Word::factory()->word('OCEANS')->create();
    Http::fake(['en.wiktionary.org/*' => Http::response(wiktionaryDefinitions(['Plural of ocean.']))]);

    $this->artisan('clues:backfill --source=wiktionary')
        ->expectsOutputToContain('OCEANS: Wiktionary had no usable English definitions.')
        ->assertFailed();
});

test('an unknown source is rejected', function () {
    Word::factory()->word('OCEAN')->create();
    Http::fake();

    $this->artisan('clues:backfill --source=encyclopedia')
        ->expectsOutputToContain('Unknown --source. Use one of: ai, wiktionary.')
        ->assertFailed();

    Http::assertNothingSent();
});
