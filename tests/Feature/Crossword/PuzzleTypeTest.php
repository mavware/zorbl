<?php

use App\Enums\PuzzleType;
use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('puzzle type is persisted to the database column', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'diamond')
        ->set('newWidth', 11)
        ->call('createPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();

    expect($crossword->puzzle_type)->toBe(PuzzleType::Diamond);
});

test('puzzle type defaults to standard in database', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('newWidth', 5)
        ->call('createPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();
    expect($crossword->puzzle_type)->toBe(PuzzleType::Standard);
});

test('puzzle type is included in API resource', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create([
        'puzzle_type' => PuzzleType::Diamond,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->getJson(route('api.v1.crosswords.index'))
        ->assertOk()
        ->assertJsonFragment(['puzzle_type' => 'diamond']);
});

test('factory supports puzzle type states', function () {
    $standard = Crossword::factory()->create();
    expect($standard->puzzle_type)->toBe(PuzzleType::Standard);

    $diamond = Crossword::factory()->diamond()->create();
    expect($diamond->puzzle_type)->toBe(PuzzleType::Diamond);
    expect($diamond->grid[0][0])->toBe('#');

    $freestyle = Crossword::factory()->freestyle()->create();
    expect($freestyle->puzzle_type)->toBe(PuzzleType::Freestyle);
});

test('PuzzleType enum has correct properties', function () {
    expect(PuzzleType::Standard->requiresSquare())->toBeTrue()
        ->and(PuzzleType::Standard->requiresOdd())->toBeFalse()
        ->and(PuzzleType::Diamond->requiresSquare())->toBeTrue()
        ->and(PuzzleType::Diamond->requiresOdd())->toBeTrue()
        ->and(PuzzleType::Freestyle->requiresSquare())->toBeFalse()
        ->and(PuzzleType::Freestyle->requiresOdd())->toBeFalse();
});

test('PuzzleType enum provides labels, descriptions, and icons', function () {
    foreach (PuzzleType::cases() as $type) {
        expect($type->label())->toBeString()->not->toBeEmpty();
        expect($type->description())->toBeString()->not->toBeEmpty();
        expect($type->icon())->toBeString()->not->toBeEmpty();
    }
});

test('freestyle puzzle stores correct type in column', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::crosswords.index')
        ->set('puzzleType', 'freestyle')
        ->set('newWidth', 10)
        ->set('newHeight', 8)
        ->call('createPuzzle')
        ->assertRedirect();

    $crossword = $user->crosswords()->first();

    expect($crossword->puzzle_type)->toBe(PuzzleType::Freestyle)
        ->and($crossword->width)->toBe(10)
        ->and($crossword->height)->toBe(8);
});

test('puzzle type badge shows on non-standard puzzle cards', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['puzzle_type' => PuzzleType::Diamond]);
    Crossword::factory()->for($user)->create(['puzzle_type' => PuzzleType::Standard]);

    $this->actingAs($user);

    $this->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Diamond');
});
