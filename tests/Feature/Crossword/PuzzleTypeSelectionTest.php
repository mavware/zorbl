<?php

use App\Enums\PuzzleType;
use App\Models\User;

test('users can create a standard puzzle', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'standard')
        ->set('newWidth', 15)
        ->call('createPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();
    expect($crossword)->not->toBeNull()
        ->and($crossword->width)->toBe(15)
        ->and($crossword->height)->toBe(15)
        ->and($crossword->metadata['puzzle_type'])->toBe('standard');
});

test('users can create a diamond puzzle', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'diamond')
        ->set('newWidth', 11)
        ->call('createPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();
    expect($crossword)->not->toBeNull()
        ->and($crossword->width)->toBe(11)
        ->and($crossword->height)->toBe(11)
        ->and($crossword->metadata['puzzle_type'])->toBe('diamond');

    $grid = $crossword->grid;
    expect($grid[0][0])->toBe('#')
        ->and($grid[0][5])->not->toBe('#')
        ->and($grid[5][5])->not->toBe('#');
});

test('diamond puzzles require odd grid size', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'diamond')
        ->set('newWidth', 10)
        ->set('newHeight', 10)
        ->call('createPuzzle')
        ->assertHasErrors('newWidth');

    expect($user->crosswords()->count())->toBe(0);
});

test('users can create a freestyle puzzle with non-square dimensions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'freestyle')
        ->set('newWidth', 10)
        ->set('newHeight', 8)
        ->call('createPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();
    expect($crossword)->not->toBeNull()
        ->and($crossword->width)->toBe(10)
        ->and($crossword->height)->toBe(8)
        ->and($crossword->metadata['puzzle_type'])->toBe('freestyle');
});

test('changing puzzle type to diamond syncs height and ensures odd size', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'freestyle')
        ->set('newWidth', 14)
        ->set('newHeight', 10)
        ->set('puzzleType', 'diamond')
        ->assertSet('newHeight', 15)
        ->assertSet('newWidth', 15);
});

test('changing puzzle type to standard syncs height to width', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'freestyle')
        ->set('newWidth', 12)
        ->set('newHeight', 8)
        ->set('puzzleType', 'standard')
        ->assertSet('newHeight', 12)
        ->assertSet('newWidth', 12);
});

test('standard type syncs height when width changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'standard')
        ->set('newWidth', 9)
        ->assertSet('newHeight', 9);
});

test('freestyle type allows independent width and height', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'freestyle')
        ->set('newWidth', 12)
        ->set('newHeight', 7)
        ->assertSet('newWidth', 12)
        ->assertSet('newHeight', 7);
});

test('puzzle type defaults to standard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->assertSet('puzzleType', 'standard');
});

test('diamond grid has correct shape', function () {
    $grid = PuzzleType::Diamond->generateGrid(7, 7);

    expect($grid[0][0])->toBe('#')
        ->and($grid[0][1])->toBe('#')
        ->and($grid[0][2])->toBe('#');

    expect($grid[0][3])->toBe(0);

    expect($grid[3])->toBe([0, 0, 0, 0, 0, 0, 0]);

    expect($grid[6][6])->toBe('#')
        ->and($grid[6][5])->toBe('#')
        ->and($grid[6][4])->toBe('#');
});

test('standard grid generates empty grid', function () {
    $grid = PuzzleType::Standard->generateGrid(5, 5);

    expect($grid)->toBe(array_fill(0, 5, array_fill(0, 5, 0)));
});

test('freestyle grid generates empty grid', function () {
    $grid = PuzzleType::Freestyle->generateGrid(8, 6);

    expect($grid)->toBe(array_fill(0, 6, array_fill(0, 8, 0)));
});

test('puzzle type enum has correct properties', function () {
    expect(PuzzleType::Standard->requiresSquare())->toBeTrue()
        ->and(PuzzleType::Standard->requiresOdd())->toBeFalse()
        ->and(PuzzleType::Diamond->requiresSquare())->toBeTrue()
        ->and(PuzzleType::Diamond->requiresOdd())->toBeTrue()
        ->and(PuzzleType::Freestyle->requiresSquare())->toBeFalse()
        ->and(PuzzleType::Freestyle->requiresOdd())->toBeFalse();
});
