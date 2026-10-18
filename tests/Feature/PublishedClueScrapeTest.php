<?php

use App\Enums\PuzzlePublication;
use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
use App\Services\PublishedClueScraper;
use App\Services\PuzzleFeeds\GuardianQuickFeed;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->travelTo(CarbonImmutable::parse('2026-10-13 09:00:00'));
    Sleep::fake();
});

/**
 * @param  list<string>  $rows
 * @param  list<string>  $across
 * @param  list<string>  $down
 */
function newsdayPuzzle(string $ymd, array $rows, array $across, array $down): string
{
    $parts = [
        'ARCHIVE', $ymd, '10/12/26 FOR ALL AGES', 'Gail Grabowski, edited by Stanley Newman',
        strlen($rows[0]), count($rows), count($across), count($down),
        implode("\n", $rows), implode("\n", $across), implode("\n", $down),
    ];

    return implode("\r\n\r\n", $parts)."\r\n\r\n\r";
}

function newsdayBatPuzzle(string $ymd = '261012'): string
{
    return newsdayPuzzle($ymd, ['BAT', 'ORE', 'WED'], ['Flying mammal', 'Mineral deposit', 'Marry'], ['Bend at the waist', 'Exist', 'Ted']);
}

function newsdayDogPuzzle(string $ymd = '261013'): string
{
    return newsdayPuzzle($ymd, ['DOG', 'ONE', 'GET'], ['Pooch', 'Single', 'Obtain'], ['Pooch', 'Single', 'Obtain']);
}

function requestedNewsdayDate(Request $request): string
{
    parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

    return (string) ($query['date'] ?? '');
}

/**
 * @param  list<array<string, mixed>>  $entries
 * @return array<string, mixed>
 */
function guardianPuzzle(int $number, array $entries, bool $solutionAvailable = true): array
{
    return ['crossword' => [
        'id' => "crosswords/quick/{$number}",
        'number' => $number,
        'name' => "Quick crossword No {$number}",
        'date' => CarbonImmutable::parse('2026-10-12')->getTimestampMs(),
        'crosswordType' => 'quick',
        'solutionAvailable' => $solutionAvailable,
        'entries' => $entries,
    ]];
}

/**
 * @param  list<string>  $group
 * @return array<string, mixed>
 */
function guardianEntry(string $id, string $clue, string $solution, array $group = []): array
{
    return ['id' => $id, 'clue' => $clue, 'direction' => 'across', 'group' => $group === [] ? [$id] : $group, 'solution' => $solution];
}

test('newsday puzzles are filed as pending clues and new words, then the feed is caught up', function () {
    Word::factory()->word('BAT')->create(['score' => 80]);
    Http::fake(fn (Request $request) => match (requestedNewsdayDate($request)) {
        '261012' => Http::response(newsdayBatPuzzle()),
        default => Http::response(newsdayDogPuzzle()),
    });

    $this->artisan('clues:scrape-published --publication=newsday --puzzles=5')
        ->expectsOutputToContain('Newsday: read 2 puzzle(s), added 8 clue(s) pending review, skipped 0.')
        ->expectsOutputToContain("Newsday: caught up; the next puzzle isn't out yet.")
        ->assertSuccessful();

    $entries = ClueEntry::query()->orderBy('id')->get();

    expect($entries->map(fn (ClueEntry $entry): string => "{$entry->answer}: {$entry->clue}")->all())
        ->toBe(['BAT: Flying mammal', 'ORE: Mineral deposit', 'WED: Marry', 'BOW: Bend at the waist', 'ARE: Exist', 'DOG: Pooch', 'ONE: Single', 'GET: Obtain'])
        ->and($entries->pluck('status')->unique()->all())->toBe([ClueEntry::STATUS_PENDING])
        ->and($entries->pluck('user_id')->unique()->all())->toBe([$this->admin->id])
        ->and($entries->pluck('crossword_id')->unique()->all())->toBe([null])
        ->and($entries->pluck('source')->unique()->all())->toBe([PuzzlePublication::Newsday->value])
        ->and($entries->first()->source_date->toDateString())->toBe('2026-10-12')
        ->and($entries->last()->source_date->toDateString())->toBe('2026-10-13')
        ->and($entries->first()->sourceLabel())->toBe('Newsday, 12 Oct 2026');

    expect(Word::where('word', 'BAT')->value('score'))->toBe(80.0)
        ->and(Word::where('word', 'DOG')->value('score'))->toBe(PublishedClueScraper::SCORE_FLOOR)
        ->and(Word::whereIn('word', ['ORE', 'WED', 'BOW', 'ARE', 'DOG', 'ONE', 'GET'])->count())->toBe(7)
        ->and(Word::where('word', 'TED')->exists())->toBeFalse()
        ->and(Cache::get('published-clue-scrape:cursor:newsday'))->toBe('2026-10-14');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_contains($request->header('User-Agent')[0] ?? '', config('app.name')));
});

test('a later run continues from the saved position and a restart does not duplicate clues', function () {
    Http::fake(fn (Request $request) => match (requestedNewsdayDate($request)) {
        '261012' => Http::response(newsdayBatPuzzle()),
        default => Http::response(newsdayDogPuzzle()),
    });

    $this->artisan('clues:scrape-published --publication=newsday')->assertSuccessful();
    expect(ClueEntry::count())->toBe(8);

    $this->artisan('clues:scrape-published --publication=newsday')
        ->expectsOutputToContain('Newsday: read 0 puzzle(s), added 0 clue(s) pending review, skipped 0.')
        ->assertSuccessful();
    Http::assertSentCount(4);

    $this->artisan('clues:scrape-published --publication=newsday --restart')
        ->expectsOutputToContain('Newsday: read 2 puzzle(s), added 0 clue(s) pending review, skipped 0.')
        ->assertSuccessful();

    expect(ClueEntry::count())->toBe(8)
        ->and(Word::count())->toBe(8);
});

test('a newsday puzzle whose clues do not match its grid is skipped', function () {
    Http::fake(fn (Request $request) => match (requestedNewsdayDate($request)) {
        '261012' => Http::response(newsdayPuzzle('261012', ['BAT', 'ORE', 'WED'], ['Flying mammal', 'Mineral deposit'], ['Bend at the waist', 'Exist', 'Ted'])),
        default => Http::response(newsdayDogPuzzle()),
    });

    $this->artisan('clues:scrape-published --publication=newsday')
        ->expectsOutputToContain('Newsday: read 1 puzzle(s), added 3 clue(s) pending review, skipped 1.')
        ->assertSuccessful();

    expect(ClueEntry::orderBy('id')->pluck('answer')->all())->toBe(['DOG', 'ONE', 'GET'])
        ->and(Cache::get('published-clue-scrape:cursor:newsday'))->toBe('2026-10-14');
});

test('a newsday date with no puzzle of its own is skipped once it is old enough', function () {
    Cache::forever('published-clue-scrape:cursor:newsday', '2026-10-01');
    Http::fake(['brainsonly.com/*' => Http::response(newsdayDogPuzzle())]);

    $this->artisan('clues:scrape-published --publication=newsday --puzzles=2')
        ->expectsOutputToContain('Newsday: read 0 puzzle(s), added 0 clue(s) pending review, skipped 2.')
        ->assertSuccessful();

    expect(Cache::get('published-clue-scrape:cursor:newsday'))->toBe('2026-10-03');
});

test('guardian quick clues lose their enumerations and multi-part answers are left out', function () {
    Http::fake([
        'theguardian.com/crosswords/series/quick' => Http::response('<a href="/crosswords/quick/17608">17,608</a> <a href="/crosswords/quick/17609">17,609</a> <a href="/crosswords/quick/17607">17,607</a>'),
        'theguardian.com/crosswords/quick/17609.json' => Http::response(guardianPuzzle(17609, [
            guardianEntry('1-across', 'Regard as likely (10)', 'ANTICIPATE'),
            guardianEntry('23-across', 'Cocktail (6,4)', 'BLOODYMARY'),
            guardianEntry('9-across', 'Cutback – introduce (anag) (9)', 'REDUCTION'),
            guardianEntry('11-across', 'Felicity – Second Coming event (7)', 'RAPTURE'),
            guardianEntry('14-across', '(The) key facts (7)', 'LOWDOWN'),
            guardianEntry('5-across', 'See 7 (3,4)', 'HOT', ['5-across', '7-down']),
            guardianEntry('7-down', 'Frankfurter (4)', 'DOGS', ['5-across', '7-down']),
        ])),
        'theguardian.com/crosswords/quick/17610.json' => Http::response('', 404),
    ]);

    $this->artisan('clues:scrape-published --publication=guardian-quick --approve')
        ->expectsOutputToContain('The Guardian Quick: read 1 puzzle(s), added 6 clue(s) approved, skipped 0.')
        ->expectsOutputToContain("The Guardian Quick: caught up; the next puzzle isn't out yet.")
        ->assertSuccessful();

    $entries = ClueEntry::query()->orderBy('id')->get();

    expect($entries->map(fn (ClueEntry $entry): string => "{$entry->answer}: {$entry->clue}")->all())
        ->toBe(['ANTICIPATE: Regard as likely', 'BLOODYMARY: Cocktail', 'REDUCTION: Cutback', 'RAPTURE: Felicity', 'RAPTURE: Second Coming event', 'LOWDOWN: Key facts'])
        ->and($entries->pluck('status')->unique()->all())->toBe([ClueEntry::STATUS_APPROVED])
        ->and($entries->pluck('reviewed_by')->unique()->all())->toBe([$this->admin->id])
        ->and($entries->first()->source)->toBe(PuzzlePublication::GuardianQuick->value)
        ->and($entries->first()->source_date->toDateString())->toBe('2026-10-12')
        ->and(Word::where('word', 'BLOODYMARY')->exists())->toBeTrue()
        ->and(Cache::get('published-clue-scrape:cursor:guardian-quick'))->toBe('17610');
});

test('a guardian puzzle whose solution is not out yet is waited for', function () {
    Cache::forever('published-clue-scrape:cursor:guardian-quick', '17609');
    Http::fake(['theguardian.com/crosswords/quick/17609.json' => Http::response(guardianPuzzle(17609, [
        guardianEntry('1-across', 'Regard as likely (10)', 'ANTICIPATE'),
    ], solutionAvailable: false))]);

    $this->artisan('clues:scrape-published --publication=guardian-quick')
        ->expectsOutputToContain('The Guardian Quick: read 0 puzzle(s), added 0 clue(s) pending review, skipped 0.')
        ->assertSuccessful();

    expect(ClueEntry::count())->toBe(0)
        ->and(Cache::get('published-clue-scrape:cursor:guardian-quick'))->toBe('17609');
});

test('guardian clues are cleaned into standalone definitions', function (string $clue, array $expected) {
    expect(GuardianQuickFeed::clues($clue))->toBe($expected);
})->with([
    'single word' => ['Regard as likely (10)', ['Regard as likely']],
    'two words' => ['Cocktail (6,4)', ['Cocktail']],
    'hyphenated' => ['Flying machine (3-3,7)', ['Flying machine']],
    'spaced' => ['Seldom encountered ( 4 )', ['Seldom encountered']],
    'no enumeration' => ['Seldom encountered', ['Seldom encountered']],
    'number inside the clue' => ['Half of 10 (4)', ['Half of 10']],
    'double definition' => ['Felicity – Second Coming event (7)', ['Felicity', 'Second Coming event']],
    'em dash and hyphen separators' => ['Tasty — nice - good (4)', ['Tasty', 'Nice', 'Good']],
    'hyphenated word kept whole' => ['Hot-air balloon (3-3,7)', ['Hot-air balloon']],
    'anagram half dropped' => ['Cutback – introduce (anag) (9)', ['Cutback']],
    'anagram spelled out' => ['(A) tiny amount – digs men (anagram) (7)', ['Tiny amount']],
    'only an anagram' => ['Digs men (anag) (7)', []],
    'leading optional word' => ['(The) key facts (7)', ['Key facts']],
    'leading optional adverb' => ['(Elegantly) unhurried (7)', ['Unhurried']],
    'trailing label kept' => ['Defeat in a game (UK slang) – washing machine cycle (5)', ['Defeat in a game (UK slang)', 'Washing machine cycle']],
    'lowercase start capitalised' => ['éclair filling (5)', ['Éclair filling']],
]);

test('every publication runs by default and one unreachable feed does not fail the run', function () {
    Http::fake([
        'brainsonly.com/*' => fn () => throw new ConnectionException('Connection timed out'),
        'theguardian.com/crosswords/series/quick' => Http::response('<a href="/crosswords/quick/17609">17,609</a>'),
        'theguardian.com/crosswords/quick/17609.json' => Http::response(guardianPuzzle(17609, [guardianEntry('1-across', 'Cocktail (6,4)', 'BLOODYMARY')])),
        'theguardian.com/crosswords/quick/17610.json' => Http::response('', 404),
    ]);

    $this->artisan('clues:scrape-published')
        ->expectsOutputToContain('Newsday: could not read the feed: Connection timed out')
        ->expectsOutputToContain('The Guardian Quick: read 1 puzzle(s), added 1 clue(s) pending review, skipped 0.')
        ->assertSuccessful();

    expect(ClueEntry::count())->toBe(1);
});

test('the run fails when every feed is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $this->artisan('clues:scrape-published')->assertFailed();
});

test('an unknown publication is rejected', function () {
    Http::fake();

    $this->artisan('clues:scrape-published --publication=nyt')
        ->expectsOutputToContain('Unknown --publication. Use one of: newsday, guardian-quick.')
        ->assertFailed();

    Http::assertNothingSent();
});

test('scraping fails without an admin to attribute clues to', function () {
    $this->admin->removeRole('Admin');
    Http::fake();

    $this->artisan('clues:scrape-published --publication=newsday')
        ->expectsOutputToContain('No Admin role user found to attribute scraped clues to.')
        ->assertFailed();

    Http::assertNothingSent();
});

test('the scraper runs daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'clues:scrape-published'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 5 * * *')
        ->and($events->first()->withoutOverlapping)->toBeTrue();
});
