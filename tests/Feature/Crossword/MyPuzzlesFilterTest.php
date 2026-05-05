<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('search filters puzzles by title', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'Ocean Breeze']);
    Crossword::factory()->for($user)->create(['title' => 'Mountain Trek']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('search', 'Ocean')
        ->assertSee('Ocean Breeze')
        ->assertDontSee('Mountain Trek');
});

test('search filters puzzles by author', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'Alpha', 'author' => 'Jane Doe']);
    Crossword::factory()->for($user)->create(['title' => 'Beta', 'author' => 'John Smith']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('search', 'Jane')
        ->assertSee('Alpha')
        ->assertDontSee('Beta');
});

test('status filter shows only published puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create(['title' => 'Published One']);
    Crossword::factory()->for($user)->create(['title' => 'Draft One', 'is_published' => false]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('status', 'published')
        ->assertSee('Published One')
        ->assertDontSee('Draft One');
});

test('status filter shows only draft puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create(['title' => 'Published One']);
    Crossword::factory()->for($user)->create(['title' => 'Draft One', 'is_published' => false]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('status', 'draft')
        ->assertSee('Draft One')
        ->assertDontSee('Published One');
});

test('sort by oldest shows oldest first', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'Older', 'created_at' => now()->subDay()]);
    Crossword::factory()->for($user)->create(['title' => 'Newer', 'created_at' => now()]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('sortBy', 'oldest')
        ->assertSeeInOrder(['Older', 'Newer']);
});

test('sort by alpha sorts alphabetically', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'Zebra']);
    Crossword::factory()->for($user)->create(['title' => 'Alpha']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('sortBy', 'alpha')
        ->assertSeeInOrder(['Alpha', 'Zebra']);
});

test('user can duplicate their own puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Original',
        'width' => 5,
        'height' => 5,
        'is_published' => true,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id);

    expect($user->crosswords()->count())->toBe(2);

    $copy = $user->crosswords()->where('id', '!=', $crossword->id)->first();
    expect($copy->title)->toBe('Original (Copy)')
        ->and($copy->width)->toBe(5)
        ->and($copy->height)->toBe(5)
        ->and($copy->is_published)->toBeFalse();
});

test('duplicated puzzle copies grid and clues', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'With Content',
        'clues_across' => [['number' => 1, 'clue' => 'Test clue']],
        'clues_down' => [['number' => 1, 'clue' => 'Down clue']],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id);

    $copy = $user->crosswords()->where('id', '!=', $crossword->id)->first();
    expect($copy->clues_across)->toBe([['number' => 1, 'clue' => 'Test clue']])
        ->and($copy->clues_down)->toBe([['number' => 1, 'clue' => 'Down clue']])
        ->and($copy->grid)->toBe($crossword->grid);
});

test('user cannot duplicate another users puzzle', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($other)->create(['is_published' => false]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id)
        ->assertForbidden();
});

test('published badge shows on published puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create(['title' => 'Pub Puzzle']);

    $this->actingAs($user);

    $this->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Published');
});

test('draft badge shows on draft puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'Draft Puzzle', 'is_published' => false]);

    $this->actingAs($user);

    $this->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Draft');
});

test('empty state changes when filters are active', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'Only One']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('search', 'nonexistent')
        ->assertSee('No matching puzzles');
});
