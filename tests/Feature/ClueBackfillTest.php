<?php

use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
use App\Services\AiWordClueWriter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    config(['services.anthropic.key' => 'test-key']);
});

function makeBackfillAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

/**
 * @param  list<string>  $clues
 */
function fakeClueWriter(array $clues): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($clues)]],
        ]),
    ]);
}

test('it writes pending clues for a word that has none and leaves clued words alone', function () {
    $admin = makeBackfillAdmin();
    $clued = Word::factory()->word('OCEAN')->create();
    ClueEntry::factory()->standalone()->for($admin)->create(['answer' => 'OCEAN']);
    Word::factory()->word('HEAVENSENT')->create();

    fakeClueWriter(['Providential', 'Like a timely windfall', 'Divinely delivered']);

    $this->artisan('clues:backfill')
        ->expectsOutputToContain('HEAVENSENT: wrote 3 clue(s).')
        ->expectsOutputToContain('Backfilled 3 clue(s) for 1 word(s), pending review.')
        ->assertSuccessful();

    $entries = ClueEntry::where('answer', 'HEAVENSENT')->orderBy('id')->get();
    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('clue')->all())->toBe(['Providential', 'Like a timely windfall', 'Divinely delivered'])
        ->and($entries->pluck('status')->unique()->all())->toBe([ClueEntry::STATUS_PENDING])
        ->and($entries->pluck('user_id')->unique()->all())->toBe([$admin->id])
        ->and($entries->pluck('crossword_id')->unique()->all())->toBe([null])
        ->and(ClueEntry::where('answer', 'OCEAN')->count())->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com')
        && str_contains($request['messages'][0]['content'], 'Answer: HEAVENSENT')
        && str_contains($request['messages'][0]['content'], 'up to 20 distinct clues'));
});

test('it stores at most the limit and drops duplicates and clues that contain the answer', function () {
    makeBackfillAdmin();
    Word::factory()->word('CAT')->create();

    $twentyFive = array_map(fn (int $i): string => "Clue number {$i}", range(1, 25));
    fakeClueWriter([...$twentyFive, 'clue number 1', 'A cat, plainly', '', 'x']);

    $this->artisan('clues:backfill')->assertSuccessful();

    expect(ClueEntry::where('answer', 'CAT')->count())->toBe(20);

    $this->artisan('clues:backfill --limit=5');
    Word::factory()->word('DOG')->create();
    $this->artisan('clues:backfill --limit=5')->assertSuccessful();

    expect(ClueEntry::where('answer', 'DOG')->count())->toBe(5);
});

test('the approve flag stores clues as approved with reviewer metadata', function () {
    $admin = makeBackfillAdmin();
    Word::factory()->word('ZEBRA')->create();
    fakeClueWriter(['Striped grazer']);

    $this->artisan('clues:backfill --approve')
        ->expectsOutputToContain('approved')
        ->assertSuccessful();

    $entry = ClueEntry::where('answer', 'ZEBRA')->firstOrFail();
    expect($entry->status)->toBe(ClueEntry::STATUS_APPROVED)
        ->and($entry->reviewed_by)->toBe($admin->id)
        ->and($entry->reviewed_at)->not->toBeNull();
});

test('it processes several words per run when asked', function () {
    makeBackfillAdmin();
    Word::factory()->word('ALPHA')->create();
    Word::factory()->word('BRAVO')->create();
    Word::factory()->word('CHARLIE')->create();
    fakeClueWriter(['Some clue']);

    $this->artisan('clues:backfill --words=2')
        ->expectsOutputToContain('for 2 word(s)')
        ->assertSuccessful();

    expect(ClueEntry::count())->toBe(2);
    Http::assertSentCount(2);
});

test('it does nothing when every word already has a clue', function () {
    $admin = makeBackfillAdmin();
    Word::factory()->word('OCEAN')->create();
    ClueEntry::factory()->standalone()->for($admin)->create(['answer' => 'OCEAN']);
    Http::fake();

    $this->artisan('clues:backfill')
        ->expectsOutputToContain('Nothing to do')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('it skips words shorter than three letters', function () {
    makeBackfillAdmin();
    Word::factory()->word('AT')->create();
    Http::fake();

    $this->artisan('clues:backfill')->expectsOutputToContain('Nothing to do');
    Http::assertNothingSent();
});

test('it fails cleanly when the AI key is missing or the AI returns nothing usable', function () {
    makeBackfillAdmin();
    Word::factory()->word('HEAVENSENT')->create();

    config(['services.anthropic.key' => null]);
    $this->artisan('clues:backfill')
        ->expectsOutputToContain('Anthropic API key is not configured')
        ->assertFailed();

    config(['services.anthropic.key' => 'test-key']);
    fakeClueWriter(['Heaven-sent gift']);
    $this->artisan('clues:backfill')
        ->expectsOutputToContain('no usable clues')
        ->assertFailed();

    expect(ClueEntry::count())->toBe(0);
});

test('it fails when there is no admin user to attribute clues to', function () {
    Word::factory()->word('HEAVENSENT')->create();
    fakeClueWriter(['Providential']);

    $this->artisan('clues:backfill')
        ->expectsOutputToContain('No Admin role user found')
        ->assertFailed();

    expect(ClueEntry::count())->toBe(0);
});

test('sanitize normalises whitespace and de-duplicates case-insensitively', function () {
    $clues = AiWordClueWriter::sanitize(['  Big   cat ', 'big cat', 42, 'Lion, e.g.'], 'TIGER', 20);

    expect($clues)->toBe(['Big cat', 'Lion, e.g.']);
});

test('the backfill is scheduled hourly behind the clue_backfill feature flag', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'clues:backfill'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');

    config(['crosswordbuilder.features.clue_backfill' => false]);
    expect($events->first()->filtersPass(app()))->toBeFalse();

    config(['crosswordbuilder.features.clue_backfill' => true]);
    expect($events->first()->filtersPass(app()))->toBeTrue();
});
