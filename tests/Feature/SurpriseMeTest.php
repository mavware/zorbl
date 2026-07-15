<?php

use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

beforeEach(function (): void {
    Config::set('profanity.words', ['borfle']);
});

// ──────────────────────────────────────────────────
// Browse page (public)
// ──────────────────────────────────────────────────

test('browse page shows surprise me button', function () {
    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Surprise Me');
});

test('surprise me redirects authenticated user to solver', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('surprise me redirects guest to public solver', function () {
    $crossword = Crossword::factory()->published()->create();

    Livewire::test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertRedirect(route('puzzles.solve', $crossword));
});

test('surprise me does nothing when no puzzles exist', function () {
    Livewire::test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('surprise me skips draft puzzles', function () {
    Crossword::factory()->create(['is_published' => false]);

    Livewire::test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('surprise me respects safe search for guests', function () {
    Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    Livewire::test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('surprise me respects safe search for users with it enabled', function () {
    $user = User::factory()->create(['safe_search_enabled' => true]);
    Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('surprise me shows profanity puzzles when safe search is off', function () {
    $user = User::factory()->create(['safe_search_enabled' => false]);
    $crossword = Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('surprise me respects blocked tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();
    $user->blockedTags()->attach($tag);

    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('surprise me picks unblocked puzzle when blocked tags exist', function () {
    $user = User::factory()->create();
    $blockedTag = Tag::factory()->create();
    $user->blockedTags()->attach($blockedTag);

    $blocked = Crossword::factory()->published()->create();
    $blocked->tags()->attach($blockedTag);

    $allowed = Crossword::factory()->published()->create();

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('surpriseMe')
        ->assertRedirect(route('crosswords.solver', $allowed));
});

// ──────────────────────────────────────────────────
// Dashboard (authenticated)
// ──────────────────────────────────────────────────

test('dashboard shows surprise me button', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Surprise Me');
});

test('dashboard surprise me redirects to solver', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('surpriseMe')
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('dashboard surprise me excludes own puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('dashboard surprise me picks other users puzzle over own', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->create();

    $other = Crossword::factory()->published()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('surpriseMe')
        ->assertRedirect(route('crosswords.solver', $other));
});

test('dashboard surprise me does nothing when no puzzles exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('dashboard surprise me respects safe search', function () {
    $user = User::factory()->create(['safe_search_enabled' => true]);
    Crossword::factory()->published()->create(['title' => 'Borfle puzzle']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('surpriseMe')
        ->assertNoRedirect();
});

test('dashboard surprise me respects blocked tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();
    $user->blockedTags()->attach($tag);

    $crossword = Crossword::factory()->published()->create();
    $crossword->tags()->attach($tag);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('surpriseMe')
        ->assertNoRedirect();
});
