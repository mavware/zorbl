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
