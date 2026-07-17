<?php

use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
use Livewire\Livewire;

test('authenticated users can view the word catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('words.index'))
        ->assertSuccessful();
});

test('guests can view the word catalog', function () {
    Word::factory()->word('OCEAN')->create();

    $this->get(route('words.index'))
        ->assertSuccessful()
        ->assertSee('OCEAN');
});

test('words are displayed in the listing', function () {
    $user = User::factory()->create();
    Word::factory()->word('OCEAN')->create();
    Word::factory()->word('RIVER')->create();

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->assertSee('OCEAN')
        ->assertSee('RIVER');
});

test('prefix search filters words', function () {
    $user = User::factory()->create();
    Word::factory()->word('OCEAN')->create();
    Word::factory()->word('OPERA')->create();
    Word::factory()->word('RIVER')->create();

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('search', 'OC')
        ->assertSee('OCEAN')
        ->assertDontSee('OPERA')
        ->assertDontSee('RIVER');
});

test('question mark wildcard matches a single letter', function () {
    $user = User::factory()->create();
    Word::factory()->word('CAT')->create();
    Word::factory()->word('COT')->create();
    Word::factory()->word('CART')->create();

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('search', 'C?T')
        ->assertSee('CAT')
        ->assertSee('COT')
        ->assertDontSee('CART');
});

test('asterisk wildcard matches any run of letters', function () {
    $user = User::factory()->create();
    Word::factory()->word('SALE')->create();
    Word::factory()->word('SIMPLE')->create();
    Word::factory()->word('RIVER')->create();

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('search', 'S*E')
        ->assertSee('SALE')
        ->assertSee('SIMPLE')
        ->assertDontSee('RIVER');
});

test('search is case insensitive', function () {
    $user = User::factory()->create();
    Word::factory()->word('OCEAN')->create();

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('search', 'oc')
        ->assertSee('OCEAN');
});

test('length filter shows only matching words', function () {
    $user = User::factory()->create();
    Word::factory()->word('OCEAN')->create(); // 5 letters
    Word::factory()->word('RIVIERA')->create(); // 7 letters

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('length', '5')
        ->assertSee('OCEAN')
        ->assertDontSee('RIVIERA');
});

test('clicking column header sorts by that field', function () {
    $user = User::factory()->create();
    Word::factory()->word('ALPHA')->create(['score' => 10.0]);
    Word::factory()->word('ZEBRA')->create(['score' => 90.0]);

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->call('sortBy', 'score')
        ->assertSet('sortField', 'score')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['ALPHA', 'ZEBRA']);
});

test('clicking same column header toggles direction', function () {
    $user = User::factory()->create();
    Word::factory()->word('ALPHA')->create(['score' => 10.0]);
    Word::factory()->word('ZEBRA')->create(['score' => 90.0]);

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->call('sortBy', 'score')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'score')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['ZEBRA', 'ALPHA']);
});

test('sort by length orders correctly', function () {
    $user = User::factory()->create();
    Word::factory()->word('ZEN')->create(); // 3 letters
    Word::factory()->word('AARDVARK')->create(); // 8 letters

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->call('sortBy', 'length')
        ->assertSeeInOrder(['ZEN', 'AARDVARK']);
});

test('the catalog clue count includes only approved clues', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Large body of water', 'user_id' => $user->id, 'status' => ClueEntry::STATUS_APPROVED]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Pacific, for one', 'user_id' => $user->id, 'status' => ClueEntry::STATUS_APPROVED]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Awaiting review', 'user_id' => $user->id, 'status' => ClueEntry::STATUS_PENDING]);

    $component = Livewire::actingAs($user)->test('pages::words.index');

    expect($component->instance()->words->firstWhere('id', $word->id)->clue_count)->toBe(2);
});

test('authenticated users can view a word detail page', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    $this->actingAs($user)
        ->get(route('words.show', $word))
        ->assertSuccessful();
});

test('guests can view a word detail page', function () {
    $word = Word::factory()->word('OCEAN')->create();

    $this->get(route('words.show', $word))
        ->assertSuccessful()
        ->assertSee('OCEAN');
});

test('the word detail page hides unapproved clues from guests', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Approved clue', 'user_id' => $user->id, 'status' => ClueEntry::STATUS_APPROVED]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Pending clue', 'user_id' => $user->id, 'status' => ClueEntry::STATUS_PENDING]);

    Livewire::test('pages::words.show', ['word' => $word])
        ->assertSee('Approved clue')
        ->assertDontSee('Pending clue')
        ->assertSee('1 clue');
});

test('word metadata is displayed on the show page', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create(['score' => 72.5]);

    Livewire::actingAs($user)
        ->test('pages::words.show', ['word' => $word])
        ->assertSee('OCEAN')
        ->assertSee('5 letters')
        ->assertSee('72.5');
});

test('related clues are shown on the show page', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Large body of water', 'user_id' => $user->id]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Pacific, for one', 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::words.show', ['word' => $word])
        ->assertSee('Large body of water')
        ->assertSee('Pacific, for one');
});

test('unrelated clues are not shown on the show page', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Large body of water', 'user_id' => $user->id]);
    ClueEntry::create(['answer' => 'RIVER', 'clue' => 'Flowing waterway', 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::words.show', ['word' => $word])
        ->assertSee('Large body of water')
        ->assertDontSee('Flowing waterway');
});
