<?php

use App\Enums\WordplayType;
use App\Filament\Pages\GenerateWordplay;
use App\Filament\Resources\WordplayEntries\Pages\ListWordplayEntries;
use App\Models\User;
use App\Models\Word;
use App\Models\WordplayEntry;
use App\Services\Wordplay\SemordnilapsFinder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('SemordnilapsFinder returns words and their reverses for a fixture set', function (): void {
    foreach (['LEPER', 'REPEL', 'STRESSED', 'DESSERTS', 'RANDOM'] as $word) {
        Word::factory()->word($word)->create();
    }

    $results = app(SemordnilapsFinder::class)->find(minLength: 5);

    expect($results)->toEqualCanonicalizing([
        ['word' => 'DESSERTS', 'reverse' => 'STRESSED'],
        ['word' => 'LEPER', 'reverse' => 'REPEL'],
    ]);
});

test('admin can load the generate page', function (): void {
    Livewire::test(GenerateWordplay::class)->assertSuccessful();
});

test('admin can load the entries list page and see saved rows', function (): void {
    $entry = WordplayEntry::create([
        'word' => 'LEPER',
        'type' => WordplayType::Semordnilap,
        'notes' => ['reverse' => 'REPEL'],
        'status' => 'saved',
    ]);

    Livewire::test(ListWordplayEntries::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$entry]);
});

test('generating semordnilaps populates the results array', function (): void {
    foreach (['LEPER', 'REPEL', 'STRESSED', 'DESSERTS'] as $word) {
        Word::factory()->word($word)->create();
    }

    Livewire::test(GenerateWordplay::class)
        ->fillForm([
            'type' => WordplayType::Semordnilap->value,
            'semordnilap_min_length' => 5,
        ])
        ->call('generate')
        ->assertNotified()
        ->assertSet('resultsType', WordplayType::Semordnilap->value)
        ->assertCount('results', 2);
});

test('save selected creates WordplayEntry rows with correct notes shape', function (): void {
    foreach (['LEPER', 'REPEL', 'STRESSED', 'DESSERTS'] as $word) {
        Word::factory()->word($word)->create();
    }

    Livewire::test(GenerateWordplay::class)
        ->fillForm([
            'type' => WordplayType::Semordnilap->value,
            'semordnilap_min_length' => 5,
        ])
        ->call('generate')
        ->set('selected', [0, 1])
        ->call('saveSelected')
        ->assertNotified();

    expect(WordplayEntry::count())->toBe(2);

    $entries = WordplayEntry::orderBy('word')->get();
    expect($entries->pluck('type')->all())->each->toBe(WordplayType::Semordnilap);
    expect($entries->pluck('word')->all())->toEqualCanonicalizing(['DESSERTS', 'LEPER']);
    expect($entries->where('word', 'LEPER')->first()->notes)->toBe(['reverse' => 'REPEL']);
});

test('save selected is idempotent on (word, type)', function (): void {
    foreach (['LEPER', 'REPEL'] as $word) {
        Word::factory()->word($word)->create();
    }

    Livewire::test(GenerateWordplay::class)
        ->fillForm([
            'type' => WordplayType::Semordnilap->value,
            'semordnilap_min_length' => 5,
        ])
        ->call('generate')
        ->set('selected', [0])
        ->call('saveSelected');

    Livewire::test(GenerateWordplay::class)
        ->fillForm([
            'type' => WordplayType::Semordnilap->value,
            'semordnilap_min_length' => 5,
        ])
        ->call('generate')
        ->set('selected', [0])
        ->call('saveSelected');

    expect(WordplayEntry::count())->toBe(1);
});
