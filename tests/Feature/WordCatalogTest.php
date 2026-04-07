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

test('guests cannot view the word catalog', function () {
    $this->get(route('words.index'))
        ->assertRedirect();
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

test('sort by score orders correctly', function () {
    $user = User::factory()->create();
    Word::factory()->word('LOW')->create(['score' => 10.0]);
    Word::factory()->word('HIGH')->create(['score' => 90.0]);

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('sort', 'score')
        ->assertSeeInOrder(['HIGH', 'LOW']);
});

test('sort by length orders correctly', function () {
    $user = User::factory()->create();
    Word::factory()->word('ZEN')->create(); // 3 letters
    Word::factory()->word('AARDVARK')->create(); // 8 letters

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->set('sort', 'length')
        ->assertSeeInOrder(['ZEN', 'AARDVARK']);
});

test('word clue count is displayed', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Large body of water', 'user_id' => $user->id]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Pacific, for one', 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::words.index')
        ->assertSee('OCEAN');
});

test('authenticated users can view a word detail page', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    $this->actingAs($user)
        ->get(route('words.show', $word))
        ->assertSuccessful();
});

test('guests cannot view a word detail page', function () {
    $word = Word::factory()->word('OCEAN')->create();

    $this->get(route('words.show', $word))
        ->assertRedirect();
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
