<?php

use App\Models\ClueEntry;
use App\Models\Crossword;
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

test('the pattern search explanation sits in the body above the search bar', function () {
    Word::factory()->word('OCEAN')->create();

    $this->get(route('words.index'))
        ->assertSuccessful()
        ->assertSeeInOrder([
            'data-page-header-group',
            '<p class="text-ink-muted text-sm leading-[1.65]" data-test="word-catalog-intro">',
            'Browse 1 crossword answers.',
            'type="search"',
        ], false);
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

test('authenticated users can add a clue from the word detail page', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    Livewire::actingAs($user)
        ->test('pages::words.show', ['word' => $word])
        ->assertSee('data-test="add-clue-button"', false)
        ->set('showAddModal', true)
        ->set('newClue', '  Large body of water  ')
        ->call('addClue')
        ->assertHasNoErrors()
        ->assertSet('showAddModal', false)
        ->assertSet('newClue', '')
        ->assertSee('Large body of water')
        ->assertSee('1 clue');

    $entry = ClueEntry::sole();
    expect($entry->answer)->toBe('OCEAN')
        ->and($entry->clue)->toBe('Large body of water')
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->crossword_id)->toBeNull()
        ->and($entry->status)->toBe(ClueEntry::STATUS_PENDING);
});

test('adding a clue from the word detail page validates the clue text', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();

    Livewire::actingAs($user)
        ->test('pages::words.show', ['word' => $word])
        ->set('newClue', '')
        ->call('addClue')
        ->assertHasErrors(['newClue' => 'required']);

    expect(ClueEntry::count())->toBe(0);
});

test('adding a duplicate clue from the word detail page is rejected', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Large body of water', 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::words.show', ['word' => $word])
        ->set('newClue', 'Large body of water')
        ->call('addClue')
        ->assertSet('addError', 'You already have this exact clue for this word in the library.');

    expect(ClueEntry::count())->toBe(1);
});

test('guests cannot add a clue from the word detail page', function () {
    $word = Word::factory()->word('OCEAN')->create();

    Livewire::test('pages::words.show', ['word' => $word])
        ->assertDontSee('data-test="add-clue-button"', false)
        ->set('newClue', 'Large body of water')
        ->call('addClue')
        ->assertForbidden();

    expect(ClueEntry::count())->toBe(0);
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

test('the word detail page shows a generated source title for clues from untitled puzzles', function () {
    $user = User::factory()->create();
    $word = Word::factory()->word('OCEAN')->create();
    $puzzle = Crossword::factory()->published()->for($user)->create(['title' => null, 'width' => 15, 'height' => 15]);
    ClueEntry::create(['answer' => 'OCEAN', 'clue' => 'Salt water', 'user_id' => $user->id, 'crossword_id' => $puzzle->id, 'status' => ClueEntry::STATUS_APPROVED]);

    Livewire::test('pages::words.show', ['word' => $word])
        ->assertOk()
        ->assertSee('Salt water')
        ->assertSee('15×15 Standard');
});
