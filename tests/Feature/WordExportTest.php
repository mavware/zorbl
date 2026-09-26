<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Models\Word;
use App\Services\WordExporter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('s3');
    config([
        'crosswordbuilder.word_export.disk' => 's3',
        'crosswordbuilder.word_export.path' => 'exports/words',
    ]);
});

/**
 * @return array<string, mixed>
 */
function readExportJson(string $path): array
{
    return json_decode((string) Storage::disk('s3')->get($path), true, 512, JSON_THROW_ON_ERROR);
}

test('the export writes one shard per word length plus a manifest', function () {
    Word::factory()->word('CAT')->create(['score' => 42.5]);
    Word::factory()->word('DOG')->create(['score' => 40]);
    Word::factory()->word('OCEAN')->create(['score' => 55.25]);

    $this->artisan('words:export-json')
        ->expectsOutputToContain('Exported 3 words and 0 approved clues across 2 shards.')
        ->assertSuccessful();

    Storage::disk('s3')->assertExists('exports/words/manifest.json');
    Storage::disk('s3')->assertExists('exports/words/words/03.json');
    Storage::disk('s3')->assertExists('exports/words/words/05.json');

    $manifest = readExportJson('exports/words/manifest.json');
    expect($manifest['version'])->toBe(WordExporter::VERSION)
        ->and($manifest['words'])->toBe(3)
        ->and($manifest['clues'])->toBe(0)
        ->and($manifest['fingerprint'])->toBeString()
        ->and(array_column($manifest['shards'], 'length'))->toBe([3, 5])
        ->and($manifest['shards'][0]['path'])->toBe('exports/words/words/03.json')
        ->and($manifest['shards'][0]['words'])->toBe(2)
        ->and($manifest['shards'][0]['sha256'])->toBe(hash('sha256', (string) Storage::disk('s3')->get('exports/words/words/03.json')));

    $shard = readExportJson('exports/words/words/03.json');
    expect($shard['length'])->toBe(3)
        ->and($shard['words'])->toBe([
            ['word' => 'CAT', 'score' => 42.5, 'clues' => []],
            ['word' => 'DOG', 'score' => 40.0, 'clues' => []],
        ]);
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

    app(WordExporter::class)->export();

    $shard = readExportJson('exports/words/words/05.json');
    expect($shard['words'])->toHaveCount(1)
        ->and($shard['words'][0]['word'])->toBe('OCEAN')
        ->and($shard['words'][0]['clues'])->toBe([
            ['text' => 'Large body of water', 'puzzle' => ['title' => 'Sea Legs', 'author' => 'Ada Lovelace']],
            ['text' => 'Pacific, for one', 'puzzle' => null],
        ]);

    $manifest = readExportJson('exports/words/manifest.json');
    expect($manifest['clues'])->toBe(2);
});

test('an approved clue whose answer is not in the word list is still exported', function () {
    $user = User::factory()->create();
    ClueEntry::factory()->standalone()->for($user)->create([
        'answer' => 'ZEBRA',
        'clue' => 'Striped grazer',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    app(WordExporter::class)->export();

    $shard = readExportJson('exports/words/words/05.json');
    expect($shard['words'])->toBe([
        ['word' => 'ZEBRA', 'score' => null, 'clues' => [['text' => 'Striped grazer', 'puzzle' => null]]],
    ]);
});

test('a repeat run with no changes writes nothing unless forced', function () {
    Word::factory()->word('CAT')->create();

    app(WordExporter::class)->export();
    $firstManifest = (string) Storage::disk('s3')->get('exports/words/manifest.json');

    $this->travel(2)->hours();

    $this->artisan('words:export-json')
        ->expectsOutputToContain('Nothing written')
        ->assertSuccessful();
    expect((string) Storage::disk('s3')->get('exports/words/manifest.json'))->toBe($firstManifest);

    $this->artisan('words:export-json --force')
        ->expectsOutputToContain('Exported 1 words')
        ->assertSuccessful();
    expect((string) Storage::disk('s3')->get('exports/words/manifest.json'))->not->toBe($firstManifest);
});

test('a change to words or approved clues triggers a rewrite', function () {
    $word = Word::factory()->word('CAT')->create(['score' => 10]);
    app(WordExporter::class)->export();

    $this->travel(1)->hour();
    $word->update(['score' => 99]);

    expect(app(WordExporter::class)->export()['skipped'])->toBeFalse()
        ->and(readExportJson('exports/words/words/03.json')['words'][0]['score'])->toBe(99.0);

    $this->travel(1)->hour();
    ClueEntry::factory()->standalone()->for(User::factory()->create())->create([
        'answer' => 'CAT',
        'clue' => 'Whiskered pet',
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    expect(app(WordExporter::class)->export()['skipped'])->toBeFalse()
        ->and(readExportJson('exports/words/words/03.json')['words'][0]['clues'][0]['text'])->toBe('Whiskered pet');
});

test('shards for lengths that no longer exist are removed', function () {
    $word = Word::factory()->word('OCEAN')->create();
    Word::factory()->word('CAT')->create();
    app(WordExporter::class)->export();
    Storage::disk('s3')->assertExists('exports/words/words/05.json');

    $this->travel(1)->hour();
    $word->delete();
    app(WordExporter::class)->export();

    Storage::disk('s3')->assertMissing('exports/words/words/05.json');
    expect(array_column(readExportJson('exports/words/manifest.json')['shards'], 'length'))->toBe([3]);
});

test('the export runs weekly on the scheduler', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'words:export-json'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * 0');
});
