<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('completed puzzle card shows faster badge when user solved faster than average', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 100,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 400,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertSee('faster');
});

test('completed puzzle card shows slower badge when user solved slower than average', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 500,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 100,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertSee('slower');
});

test('no comparison badge shown when user is the only solver', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 200,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertDontSee('faster')
        ->assertDontSee('slower');
});

test('no comparison badge shown for in-progress attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => false,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 200,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertDontSee('faster')
        ->assertDontSee('slower');
});
