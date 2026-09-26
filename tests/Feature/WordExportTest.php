<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use App\Services\WordExporter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the manifest lists one shard per word length', function () {
    Word::factory()->word('CAT')->create(['score' => 42.5]);
    Word::factory()->word('DOG')->create(['score' => 40]);
    Word::factory()->word('OCEAN')->create(['score' => 55.25]);

    $manifest = $this->getJson(route('api.v1.words.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->json();

    expect($manifest['version'])->toBe(WordExporter::VERSION)
        ->and($manifest['words'])->toBe(3)
        ->and($manifest['clues'])->toBe(0)
        ->and($manifest['fingerprint'])->toBeString()
        ->and(array_column($manifest['shards'], 'length'))->toBe([3, 5])
        ->and($manifest['shards'][0]['url'])->toBe(route('api.v1.words.shard', 3))
        ->and($manifest['shards'][0]['words'])->toBe(2);

    $shardResponse = $this->get(route('api.v1.words.shard', 3))->assertOk();

    expect($manifest['shards'][0]['sha256'])->toBe(hash('sha256', $shardResponse->getContent()))
        ->and($shardResponse->json('length'))->toBe(3)
        ->and($shardResponse->json('words'))->toBe([
            ['word' => 'CAT', 'score' => 42.5, 'clues' => []],
            ['word' => 'DOG', 'score' => 40.0, 'clues' => []],
        ]);
});

test('responses are publicly cacheable with an etag', function () {
    Word::factory()->word('CAT')->create();

    $response = $this->get(route('api.v1.words.manifest'))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=600')
        ->and($response->headers->get('ETag'))->not->toBeNull();

    $this->get(route('api.v1.words.manifest'), ['If-None-Match' => $response->headers->get('ETag')])
        ->assertStatus(304);
});

test('a length with no words returns not found', function () {
    Word::factory()->word('CAT')->create();

    $this->get(route('api.v1.words.shard', 7))->assertNotFound();
});

test('shards include approved clues with puzzle attribution and leave out pending ones', function () {
    Word::factory()->word('OCEAN')->create(['score' => 55]);
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Sea Legs',
        'author' => 'Ada Lovelace',
    ]);

    ClueEntry::factory()->for($crossword)->for($constructor)->create([
        'answer' => 'OCEAN',
        'clue' => 'Large body of water',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);
    ClueEntry::factory()->standalone()->for($constructor)->create([
        'answer' => 'OCEAN',
        'clue' => 'Pacific, for one',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);
    ClueEntry::factory()->standalone()->for($constructor)->create([
        'answer' => 'OCEAN',
        'clue' => 'Not yet reviewed',
        'status' => ClueEntry::STATUS_PENDING,
    ]);

    $words = $this->getJson(route('api.v1.words.shard', 5))->assertOk()->json('words');

    expect($words)->toHaveCount(1)
        ->and($words[0]['word'])->toBe('OCEAN')
        ->and($words[0]['clues'])->toBe([
            ['text' => 'Large body of water', 'puzzle' => ['title' => 'Sea Legs', 'author' => 'Ada Lovelace']],
            ['text' => 'Pacific, for one', 'puzzle' => null],
        ]);

    expect($this->getJson(route('api.v1.words.manifest'))->json('clues'))->toBe(2);
});

test('an approved clue whose answer is not in the word list is still included', function () {
    ClueEntry::factory()->standalone()->for(User::factory()->create())->create([
        'answer' => 'ZEBRA',
        'clue' => 'Striped grazer',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    expect($this->getJson(route('api.v1.words.shard', 5))->assertOk()->json('words'))->toBe([
        ['word' => 'ZEBRA', 'score' => null, 'clues' => [['text' => 'Striped grazer', 'puzzle' => null]]],
    ]);
});

test('repeat requests are served from the cache without rebuilding', function () {
    Word::factory()->word('CAT')->create();
    $this->get(route('api.v1.words.manifest'))->assertOk();

    DB::enableQueryLog();
    $this->get(route('api.v1.words.manifest'))->assertOk();
    $this->get(route('api.v1.words.shard', 3))->assertOk();

    expect(DB::getQueryLog())->toBe([]);
});

test('a change to words or approved clues is served once the fingerprint refreshes', function () {
    $word = Word::factory()->word('CAT')->create(['score' => 10]);
    $this->get(route('api.v1.words.shard', 3))->assertOk();

    $this->travel(1)->hour();
    $word->update(['score' => 99]);

    expect($this->getJson(route('api.v1.words.shard', 3))->json('words.0.score'))->toBe(99.0);

    $this->travel(1)->hour();
    ClueEntry::factory()->standalone()->for(User::factory()->create())->create([
        'answer' => 'CAT',
        'clue' => 'Whiskered pet',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    expect($this->getJson(route('api.v1.words.shard', 3))->json('words.0.clues.0.text'))->toBe('Whiskered pet');
});

test('lengths that no longer exist drop out of the manifest', function () {
    $word = Word::factory()->word('OCEAN')->create();
    Word::factory()->word('CAT')->create();
    $this->get(route('api.v1.words.shard', 5))->assertOk();

    $this->travel(1)->hour();
    $word->delete();

    expect(array_column($this->getJson(route('api.v1.words.manifest'))->json('shards'), 'length'))->toBe([3]);
    $this->get(route('api.v1.words.shard', 5))->assertNotFound();
});

test('the command rebuilds immediately and warms the cache', function () {
    Word::factory()->word('CAT')->create(['score' => 10]);
    $this->get(route('api.v1.words.shard', 3))->assertOk();

    Word::query()->update(['score' => 50]);

    $this->artisan('words:export-json')
        ->expectsOutputToContain('Cached 1 words and 0 approved clues across 1 shards.')
        ->assertSuccessful();

    DB::enableQueryLog();
    expect($this->getJson(route('api.v1.words.shard', 3))->json('words.0.score'))->toBe(50.0)
        ->and(DB::getQueryLog())->toBe([]);
});

test('the export is no longer scheduled', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'words:export-json'));

    expect($events)->toBeEmpty();
});
