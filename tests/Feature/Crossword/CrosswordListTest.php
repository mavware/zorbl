<?php

use App\Models\Crossword;
use App\Models\User;

test('guests are redirected to login', function () {
    $this->get(route('crosswords.index'))->assertRedirect(route('login'));
});

test('authenticated users can view their puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['title' => 'My Puzzle']);

    $this->actingAs($user);

    $this->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('My Puzzle');
});

test('users cannot see other users puzzles', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Crossword::factory()->for($other)->create(['title' => 'Secret Puzzle']);

    $this->actingAs($user);

    $this->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('Secret Puzzle');
});

test('users can create a new puzzle', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('newWidth', 5)
        ->set('newHeight', 5)
        ->call('createPuzzle')
        ->assertRedirect();

    expect($user->crosswords()->count())->toBe(1);

    $crossword = $user->crosswords()->first();
    expect($crossword->width)->toBe(5)
        ->and($crossword->height)->toBe(5);
});

test('users can delete their puzzles', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->call('deletePuzzle', $crossword->id);

    expect(Crossword::find($crossword->id))->toBeNull();
});

test('users cannot delete other users puzzles', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($other)->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->call('deletePuzzle', $crossword->id)
        ->assertForbidden();

    expect(Crossword::find($crossword->id))->not->toBeNull();
});

test('users can duplicate their own puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Original Puzzle',
        'notes' => 'Some notes',
        'width' => 5,
        'height' => 5,
        'clues_across' => [['number' => 1, 'clue' => 'Test clue']],
        'clues_down' => [['number' => 1, 'clue' => 'Down clue']],
        'is_published' => true,
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id)
        ->assertRedirect();

    expect($user->crosswords()->count())->toBe(2);

    $duplicate = $user->crosswords()->where('id', '!=', $crossword->id)->first();
    expect($duplicate->title)->toBe('Copy of Original Puzzle')
        ->and($duplicate->notes)->toBe('Some notes')
        ->and($duplicate->width)->toBe(5)
        ->and($duplicate->height)->toBe(5)
        ->and($duplicate->clues_across)->toBe([['number' => 1, 'clue' => 'Test clue']])
        ->and($duplicate->clues_down)->toBe([['number' => 1, 'clue' => 'Down clue']])
        ->and($duplicate->is_published)->toBeFalse();
});

test('duplicated puzzle is always a draft', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create([
        'title' => 'Published Puzzle',
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id)
        ->assertRedirect();

    $duplicate = $user->crosswords()->where('id', '!=', $crossword->id)->first();
    expect($duplicate->is_published)->toBeFalse();
});

test('users cannot duplicate other users unpublished puzzles', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($other)->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id)
        ->assertForbidden();

    expect($user->crosswords()->count())->toBe(0);
});

test('duplicate respects puzzle limit for free users', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->count(5)->create();

    $crossword = $user->crosswords()->first();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->call('duplicatePuzzle', $crossword->id)
        ->assertNoRedirect();

    expect($user->crosswords()->count())->toBe(5);
});
