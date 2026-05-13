<?php

use App\Models\Crossword;
use App\Models\User;
use App\Support\ProfanityFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    // Use harmless sentinels in tests so we don't ship real profanity in the codebase.
    Config::set('profanity.words', ['borfle', 'snitwit']);
});

test('filter matches a banned word as a whole word', function () {
    $filter = new ProfanityFilter;

    expect($filter->contains('hello borfle world'))->toBeTrue();
    expect($filter->contains('BORFLE'))->toBeTrue();
});

test('filter does not match a banned word embedded in another word (no Scunthorpe)', function () {
    $filter = new ProfanityFilter;

    expect($filter->contains('cyborfle'))->toBeFalse();
    expect($filter->contains('borfler'))->toBeFalse();
});

test('filter returns false for empty / null input', function () {
    $filter = new ProfanityFilter;

    expect($filter->contains(null))->toBeFalse();
    expect($filter->contains(''))->toBeFalse();
});

test('containsAny walks nested clue arrays', function () {
    $filter = new ProfanityFilter;

    expect($filter->containsAny(['fine clue', ['nested borfle clue']]))->toBeTrue();
    expect($filter->containsAny(['fine', 'clean', ['also fine']]))->toBeFalse();
});

test('registration rejects a profane name', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Captain Borfle',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('name');
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('registration default-enables safe search', function () {
    $this->post(route('register.store'), [
        'name' => 'Clean Person',
        'email' => 'clean@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'clean@example.com')->firstOrFail();
    expect($user->safe_search_enabled)->toBeTrue();
});

test('profile update rejects a profane name', function () {
    $user = User::factory()->create();

    Livewire\Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', 'Sir Snitwit')
        ->call('updateProfileInformation')
        ->assertHasErrors('name');

    expect($user->fresh()->name)->not->toBe('Sir Snitwit');
});

test('profile update can toggle safe search off', function () {
    $user = User::factory()->create(['safe_search_enabled' => true]);

    Livewire\Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('safeSearchEnabled', false)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->safe_search_enabled)->toBeFalse();
});

test('saving a puzzle with a profane title flags it', function () {
    $crossword = Crossword::factory()->create(['title' => 'Goofy borfle puzzle']);

    expect($crossword->fresh()->contains_profanity)->toBeTrue();
});

test('saving a puzzle with a clean title and clues does not flag it', function () {
    $crossword = Crossword::factory()->create([
        'title' => 'A wholesome puzzle',
        'clues_across' => ['1' => 'A delightful clue', '2' => 'Another fine clue'],
        'clues_down' => ['1' => 'Yet another clue'],
    ]);

    expect($crossword->fresh()->contains_profanity)->toBeFalse();
});

test('saving a puzzle with a profane clue flags it', function () {
    $crossword = Crossword::factory()->create([
        'title' => 'Polite title',
        'clues_across' => ['1' => 'Quite the borfle, this clue'],
    ]);

    expect($crossword->fresh()->contains_profanity)->toBeTrue();
});

test('safe-search users do not see flagged puzzles in the browse query', function () {
    $user = User::factory()->create(['safe_search_enabled' => true]);
    $clean = Crossword::factory()->published()->create(['title' => 'Clean puzzle']);
    $dirty = Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    $results = Crossword::query()->where('is_published', true)->safeFor($user)->pluck('id')->all();

    expect($results)->toContain($clean->id)
        ->and($results)->not->toContain($dirty->id);
});

test('safe-search-off users see flagged puzzles in the browse query', function () {
    $user = User::factory()->create(['safe_search_enabled' => false]);
    $dirty = Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    $results = Crossword::query()->where('is_published', true)->safeFor($user)->pluck('id')->all();

    expect($results)->toContain($dirty->id);
});

test('owners see their own flagged puzzles even with safe search on', function () {
    $owner = User::factory()->create(['safe_search_enabled' => true]);
    $dirty = Crossword::factory()->for($owner)->published()->create(['title' => 'Borfle puzzle']);

    $results = Crossword::query()->where('is_published', true)->safeFor($owner)->pluck('id')->all();

    expect($results)->toContain($dirty->id);
});

test('public solver returns 404 for safe-search users on a flagged puzzle', function () {
    $owner = User::factory()->create(['safe_search_enabled' => false]);
    $dirty = Crossword::factory()->for($owner)->published()->create(['title' => 'Borfle puzzle']);

    // Guest (safe-search defaults to on) gets blocked.
    $this->get(route('puzzles.solve', $dirty))->assertNotFound();
});

test('guest solver still works for clean puzzles', function () {
    $dirty = Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);
    $clean = Crossword::factory()->published()->create(['title' => 'Clean puzzle']);

    $this->get(route('puzzles.solve', $clean))->assertOk();
    $this->get(route('puzzles.solve', $dirty))->assertNotFound();
});

test('sitemap excludes profanity-flagged puzzles', function () {
    Cache::forget('sitemap.xml');
    $clean = Crossword::factory()->published()->create(['title' => 'Clean puzzle']);
    $dirty = Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)
        ->toContain(route('puzzles.solve', $clean->id))
        ->not->toContain(route('puzzles.solve', $dirty->id));
});
