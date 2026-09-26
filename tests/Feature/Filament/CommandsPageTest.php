<?php

use App\Filament\Pages\Commands;
use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
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
        ->assertSee('Run clues:backfill');
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

test('the backfill action passes its options to the command', function (): void {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['Providential', 'Divinely delivered'])]],
        ]),
    ]);
    Word::factory()->word('HEAVENSENT')->create();

    Livewire::test(Commands::class)
        ->callAction('backfillClues', ['words' => 1, 'limit' => 5, 'approve' => true])
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
