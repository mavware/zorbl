<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('pencil cells are saved with progress', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', [['A', ''], ['', '']], false, 30, ['0,0' => true]);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->pencil_cells)->toBe(['0,0' => true]);
});

test('pencil cells are loaded on mount', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => [['A', ''], ['', '']],
        'pencil_cells' => ['0,0' => true],
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('pencilCells', ['0,0' => true]);
});

test('pencil cells default to empty array when null', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'pencil_cells' => null,
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('pencilCells', []);
});

test('pencil cells are cleared when progress is saved without them', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => [['A', ''], ['', '']],
        'pencil_cells' => ['0,0' => true],
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', [['A', 'B'], ['C', '']], false, 60, []);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->pencil_cells)->toBe([]);
});

test('multiple pencil cells can be tracked', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    $pencilCells = ['0,0' => true, '0,1' => true, '1,0' => true];

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', [['A', 'B'], ['C', '']], false, 45, $pencilCells);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->pencil_cells)->toBe($pencilCells);
});

test('solver page renders pencil mode toggle button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solver', $crossword))
        ->assertOk()
        ->assertSee('Pencil mode');
});
