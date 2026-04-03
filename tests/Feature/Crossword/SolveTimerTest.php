<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

test('puzzle attempt records started_at on creation', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)->get(route('crosswords.solver', $crossword));

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->started_at)->not->toBeNull();
});

test('save progress stores solve time', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $attempt = PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', [['A', 'B'], ['C', '']], false, 120);

    $attempt->refresh();
    expect($attempt->solve_time_seconds)->toBe(120);
});

test('completing a puzzle records completed_at and solve time', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $attempt = PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution(2, 2),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', [['A', 'B'], ['C', 'D']], true, 300);

    $attempt->refresh();
    expect($attempt->is_completed)->toBeTrue()
        ->and($attempt->completed_at)->not->toBeNull()
        ->and($attempt->solve_time_seconds)->toBe(300);
});

test('formatted solve time displays correctly', function () {
    $attempt = new PuzzleAttempt;

    $attempt->solve_time_seconds = 62;
    expect($attempt->formattedSolveTime())->toBe('1:02');

    $attempt->solve_time_seconds = 3661;
    expect($attempt->formattedSolveTime())->toBe('1:01:01');

    $attempt->solve_time_seconds = 0;
    expect($attempt->formattedSolveTime())->toBe('0:00');

    $attempt->solve_time_seconds = null;
    expect($attempt->formattedSolveTime())->toBeNull();
});

test('stats page is accessible and shows data', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
        'solve_time_seconds' => 180,
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.stats'))
        ->assertOk()
        ->assertSee('Solve Statistics')
        ->assertSee('3:00');
});

test('stats page shows empty state without completed puzzles', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.stats'))
        ->assertOk()
        ->assertSee('Complete puzzles to see your solve history');
});

test('solving page shows stats link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSee('Stats');
});
