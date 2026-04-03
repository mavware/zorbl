<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('constructor can save prefilled cells', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('savePrefilled', [['A', ''], ['', 'D']]);

    $crossword->refresh();
    expect($crossword->prefilled)->toBe([['A', ''], ['', 'D']]);
});

test('empty prefilled grid is stored as null', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => [['A', ''], ['', '']],
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('savePrefilled', [['', ''], ['', '']]);

    $crossword->refresh();
    expect($crossword->prefilled)->toBeNull();
});

test('solver gets prefilled values as initial progress', function () {
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => [['A', ''], ['', 'D']],
    ]);

    $solver = User::factory()->create();
    $this->actingAs($solver);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('progress', [['A', ''], ['', 'D']]);
});

test('solver without prefilled gets empty progress', function () {
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => null,
    ]);

    $solver = User::factory()->create();
    $this->actingAs($solver);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('progress', [['', ''], ['', '']]);
});

test('existing attempt is not overwritten by prefilled values', function () {
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'prefilled' => [['A', ''], ['', 'D']],
    ]);

    $solver = User::factory()->create();

    // Create an existing attempt with some progress
    PuzzleAttempt::factory()->for($solver)->for($crossword)->create([
        'progress' => [['A', 'B'], ['C', '']],
        'started_at' => now(),
    ]);

    $this->actingAs($solver);

    // Existing attempt progress should be preserved, not overwritten by prefilled
    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('progress', [['A', 'B'], ['C', '']]);
});

test('editor shows prefill button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSee('Pre-fill cells for solvers');
});
