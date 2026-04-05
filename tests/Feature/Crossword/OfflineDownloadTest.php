<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('solver can download puzzle as ipuz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'title' => 'Test Puzzle',
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('exportIpuz')
        ->assertFileDownloaded('test-puzzle.ipuz');
});

test('solver can download puzzle as puz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'title' => 'Test Puzzle',
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('exportPuz')
        ->assertFileDownloaded('test-puzzle.puz');
});

test('solver can download puzzle as jpz', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
        'title' => 'Test Puzzle',
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('exportJpz')
        ->assertFileDownloaded('test-puzzle.jpz');
});

test('solver page shows download button', function () {
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
        ->assertSee('Download for offline solving');
});

test('unauthenticated user cannot download puzzle', function () {
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->get(route('crosswords.solver', $crossword))
        ->assertRedirect();
});
