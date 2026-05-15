<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('saveProgress persists revealed cells to puzzle attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);
    $progress[0][0] = 'A';
    $revealedCells = ['0,0' => true];

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, false, 10, [], $revealedCells);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->revealed_cells)->toBe(['0,0' => true]);
});

test('solver loads revealed cells from existing attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $revealedCells = ['1,2' => true, '0,0' => true];

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'progress' => Crossword::emptySolution(3, 3),
        'revealed_cells' => $revealedCells,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('revealedCells', $revealedCells);
});

test('revealed cells default to empty array for attempts without them', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'progress' => Crossword::emptySolution(3, 3),
        'revealed_cells' => null,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('revealedCells', []);
});

test('revealed cells are cleared when saving empty revealed state', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'progress' => Crossword::emptySolution(3, 3),
        'revealed_cells' => ['0,0' => true],
    ]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, false, 0, [], []);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->revealed_cells)->toBe([]);
});

test('revealed cells accumulate across multiple saves', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);
    $progress[0][0] = 'A';

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, false, 5, [], ['0,0' => true]);

    $progress[1][1] = 'B';

    $component->call('saveProgress', $progress, false, 10, [], ['0,0' => true, '1,1' => true]);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->revealed_cells)->toBe(['0,0' => true, '1,1' => true]);
});
