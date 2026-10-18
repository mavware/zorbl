<?php

use App\Filament\Pages\Commands;
use App\Models\ClueEntry;
use App\Models\Template;
use App\Models\User;
use App\Models\Word;
use App\Services\SpreadWordlistImporter;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can load the commands page', function (): void {
    $this->get(Commands::getUrl())
        ->assertSuccessful()
        ->assertSee('Run words:export-json')
        ->assertSee('Run clues:backfill')
        ->assertSee('Run words:import-spread-wordlist')
        ->assertSee('Run words:import-wiktionary')
        ->assertSee('Run clues:scrape-published')
        ->assertSee('Run templates:generate');
});

test('non-admins cannot load the commands page', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(Commands::getUrl())
        ->assertForbidden();
});

test('the export action rebuilds the word list and shows the output', function (): void {
    Word::factory()->word('CAT')->create();

    Livewire::test(Commands::class)
        ->callAction('exportWords')
        ->assertNotified('words:export-json finished')
        ->assertSet('lastCommand', 'words:export-json')
        ->assertSee('Cached 1 words and 0 approved clues across 1 shards.');
});

test('the spread wordlist action rescores the word list and shows the output', function (): void {
    $this->mock(SpreadWordlistImporter::class)
        ->shouldReceive('import')
        ->once()
        ->with(null)
        ->andReturn(['entries' => 304410, 'imported' => 302409, 'skipped' => 2001, 'unlisted' => 168763]);

    Livewire::test(Commands::class)
        ->callAction('importSpreadWordlist')
        ->assertNotified('words:import-spread-wordlist finished')
        ->assertSet('lastCommand', 'words:import-spread-wordlist')
        ->assertSee('Read 304410 entries: scored 302409 word(s), skipped 2001')
        ->assertSee('168763 word(s) not in the list are kept at the unvetted score.');
});

test('the backfill action passes its options to the command', function (): void {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['Providential', 'Divinely delivered'])]],
        ]),
    ]);
    Word::factory()->word('HEAVENSENT')->create();

    Livewire::test(Commands::class)
        ->callAction('backfillClues', ['source' => 'ai', 'words' => 1, 'limit' => 5, 'approve' => true])
        ->assertHasNoFormErrors()
        ->assertNotified('clues:backfill finished')
        ->assertSee('HEAVENSENT: wrote 2 clue(s).');

    expect(ClueEntry::where('answer', 'HEAVENSENT')->where('status', ClueEntry::STATUS_APPROVED)->count())->toBe(2);
});

test('the backfill action caps how many words one run can process', function (): void {
    Livewire::test(Commands::class)
        ->callAction('backfillClues', ['words' => Commands::MAX_BACKFILL_WORDS + 1, 'limit' => 5, 'approve' => false])
        ->assertHasFormErrors(['words' => 'max']);
});

test('the backfill action can use wiktionary definitions', function (): void {
    Http::fake(['en.wiktionary.org/*' => Http::response([
        'en' => [['partOfSpeech' => 'Noun', 'language' => 'English', 'definitions' => [['definition' => 'A large body of salt water.']]]],
    ])]);
    Word::factory()->word('OCEAN')->create();

    Livewire::test(Commands::class)
        ->callAction('backfillClues', ['source' => 'wiktionary', 'words' => 1, 'limit' => 5, 'approve' => false])
        ->assertNotified('clues:backfill finished')
        ->assertSee('OCEAN: wrote 1 clue(s).');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.anthropic.com'));
});

test('the import action reads the requested number of wiktionary pages', function (): void {
    Http::fake(['en.wiktionary.org/*' => Http::response([
        'continue' => ['cmcontinue' => 'next'],
        'query' => ['categorymembers' => [['ns' => 0, 'title' => 'zebra']]],
    ])]);

    Livewire::test(Commands::class)
        ->callAction('importWords', ['pages' => 2, 'restart' => false])
        ->assertHasNoFormErrors()
        ->assertNotified('words:import-wiktionary finished')
        ->assertSee('Read 2 page(s), 2 entries: added 1 new word(s).');

    expect(Word::where('word', 'ZEBRA')->exists())->toBeTrue();
});

test('the import action caps how many pages one run can read', function (): void {
    Livewire::test(Commands::class)
        ->callAction('importWords', ['pages' => Commands::MAX_IMPORT_PAGES + 1, 'restart' => false])
        ->assertHasFormErrors(['pages' => 'max']);
});

test('the scrape action reads the chosen publication and shows the output', function (): void {
    Http::fake([
        'brainsonly.com/*' => Http::response(implode("\n\n", [
            'ARCHIVE', now()->subDay()->format('ymd'), '10/12/26 FOR ALL AGES', 'Gail Grabowski, edited by Stanley Newman',
            3, 3, 3, 3, "BAT\nORE\nWED", "Flying mammal\nMineral deposit\nMarry", "Bend at the waist\nExist\nKennedy of the Senate",
        ])),
    ]);

    Livewire::test(Commands::class)
        ->callAction('scrapePublishedClues', ['publication' => 'newsday', 'puzzles' => 1, 'approve' => true])
        ->assertHasNoFormErrors()
        ->assertNotified('clues:scrape-published finished')
        ->assertSee('Newsday: read 1 puzzle(s), added 6 clue(s) approved, skipped 0.');

    expect(ClueEntry::where('source', 'newsday')->where('status', ClueEntry::STATUS_APPROVED)->count())->toBe(6);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'theguardian.com'));
});

test('the scrape action caps how many puzzles one run can read', function (): void {
    Livewire::test(Commands::class)
        ->callAction('scrapePublishedClues', ['publication' => 'all', 'puzzles' => Commands::MAX_SCRAPE_PUZZLES + 1, 'approve' => false])
        ->assertHasFormErrors(['puzzles' => 'max']);
});

test('the generate templates action builds a rectangular grid through the command', function (): void {
    Livewire::test(Commands::class)
        ->callAction('generateTemplates', ['width' => 7, 'height' => 9, 'style' => 'standard', 'count' => 1, 'seed' => 5, 'activate' => true])
        ->assertHasNoFormErrors()
        ->assertNotified('templates:generate finished')
        ->assertSet('lastCommand', 'templates:generate')
        ->assertSee('Saved 1 active template(s).');

    $template = Template::sole();

    expect($template->width)->toBe(7)
        ->and($template->height)->toBe(9)
        ->and($template->is_active)->toBeTrue();
});

test('the generate templates action caps the count and the size', function (): void {
    Livewire::test(Commands::class)
        ->callAction('generateTemplates', ['width' => Commands::MAX_GENERATE_SIZE + 1, 'style' => 'standard', 'count' => Commands::MAX_GENERATE_TEMPLATES + 1])
        ->assertHasFormErrors(['width' => 'max', 'count' => 'max']);

    expect(Template::count())->toBe(0);
});
