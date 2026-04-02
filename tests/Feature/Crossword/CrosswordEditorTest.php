<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access the editor', function () {
    $crossword = Crossword::factory()->create();

    $this->get(route('crosswords.editor', $crossword))->assertRedirect(route('login'));
});

test('users can view their own puzzle editor', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['title' => 'Test Puzzle']);

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSee('Test Puzzle');
});

test('users cannot view other users puzzle editor', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($other)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))->assertForbidden();
});

test('users can save grid state', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 3,
        'height' => 3,
    ]);

    $this->actingAs($user);

    $newGrid = [
        [1, 2, '#'],
        [3, 0, 0],
        ['#', 4, 0],
    ];
    $newSolution = [
        ['A', 'B', '#'],
        ['C', 'D', 'E'],
        ['#', 'F', 'G'],
    ];

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('save', $newGrid, $newSolution, null, [['number' => 1, 'clue' => 'Test']], [])
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->grid)->toBe($newGrid)
        ->and($crossword->solution)->toBe($newSolution)
        ->and($crossword->clues_across[0]['clue'])->toBe('Test');
});

test('users can update metadata', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('title', 'Updated Title')
        ->set('author', 'New Author')
        ->call('saveMetadata')
        ->assertDispatched('saved');

    $crossword->refresh();
    expect($crossword->title)->toBe('Updated Title')
        ->and($crossword->author)->toBe('New Author');
});

test('users can resize the grid', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 5,
        'height' => 5,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('resizeWidth', 7)
        ->set('resizeHeight', 7)
        ->call('resizeGrid')
        ->assertDispatched('grid-resized');

    $crossword->refresh();
    expect($crossword->width)->toBe(7)
        ->and($crossword->height)->toBe(7)
        ->and($crossword->grid)->toHaveCount(7)
        ->and($crossword->grid[0])->toHaveCount(7);
});

test('users can toggle puzzle published state', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    expect($crossword->is_published)->toBeFalse();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true);

    $crossword->refresh();
    expect($crossword->is_published)->toBeTrue();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', false);

    $crossword->refresh();
    expect($crossword->is_published)->toBeFalse();
});
