<?php

use App\Filament\Resources\Words\Pages\CreateWord;
use App\Filament\Resources\Words\Pages\EditWord;
use App\Filament\Resources\Words\Pages\ListWords;
use App\Models\User;
use App\Models\Word;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view the word list', function () {
    $words = Word::factory()->count(3)->create();

    $this->get('/admin/words')->assertSuccessful();

    Livewire::test(ListWords::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($words);
});

test('the word list searches by prefix', function () {
    $match = Word::factory()->word('OCEAN')->create();
    $other = Word::factory()->word('RIVER')->create();

    Livewire::test(ListWords::class)
        ->searchTable('oce')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

test('the word list can filter by length', function () {
    $short = Word::factory()->word('CAT')->create();
    $long = Word::factory()->word('OCEAN')->create();

    Livewire::test(ListWords::class)
        ->filterTable('length', 3)
        ->assertCanSeeTableRecords([$short])
        ->assertCanNotSeeTableRecords([$long]);
});

test('the word list can filter by minimum score', function () {
    $strong = Word::factory()->word('OCEAN')->create(['score' => 80]);
    $weak = Word::factory()->word('QOPHS')->create(['score' => 20]);

    Livewire::test(ListWords::class)
        ->filterTable('score', ['min_score' => 50])
        ->assertCanSeeTableRecords([$strong])
        ->assertCanNotSeeTableRecords([$weak]);
});

test('admin can create a word', function () {
    Livewire::test(CreateWord::class)
        ->fillForm([
            'word' => 'ocean',
            'score' => 62.5,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $word = Word::where('word', 'OCEAN')->first();

    expect($word)->not->toBeNull()
        ->and($word->length)->toBe(5)
        ->and($word->score)->toBe(62.5);
});

test('creating a word requires a word and a score', function () {
    Livewire::test(CreateWord::class)
        ->fillForm([
            'word' => null,
            'score' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'word' => 'required',
            'score' => 'required',
        ])
        ->assertNotNotified();
});

test('a word must be letters only and within the crossword length range', function (string $candidate, string $rule) {
    Livewire::test(CreateWord::class)
        ->fillForm([
            'word' => $candidate,
            'score' => 50,
        ])
        ->call('create')
        ->assertHasFormErrors(['word' => $rule])
        ->assertNotNotified();
})->with([
    'digits' => ['OCE4N', 'alpha'],
    'too short' => ['AT', 'min'],
    'too long' => [str_repeat('A', Word::MAX_LENGTH + 1), 'max'],
]);

test('words must be unique', function () {
    Word::factory()->word('OCEAN')->create();

    Livewire::test(CreateWord::class)
        ->fillForm([
            'word' => 'OCEAN',
            'score' => 50,
        ])
        ->call('create')
        ->assertHasFormErrors(['word' => 'unique'])
        ->assertNotNotified();
});

test('admin can edit a word and the derived length follows', function () {
    $word = Word::factory()->word('CAT')->create();

    Livewire::test(EditWord::class, ['record' => $word->id])
        ->fillForm([
            'word' => 'cattle',
            'score' => 71,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $word->refresh();

    expect($word->word)->toBe('CATTLE')
        ->and($word->length)->toBe(6)
        ->and($word->score)->toBe(71.0);
});

test('admin can delete a word', function () {
    $word = Word::factory()->word('TEMPORARY')->create();

    Livewire::test(EditWord::class, ['record' => $word->id])
        ->callAction('delete')
        ->assertNotified();

    expect(Word::where('word', 'TEMPORARY')->exists())->toBeFalse();
});

test('non-admin cannot access the word list admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/words')
        ->assertForbidden();
});
