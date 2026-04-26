<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('generateShareText returns formatted share text with title, size, time, and link', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Sunday Funday',
        'width' => 15,
        'height' => 15,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 225,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $shareText = invade($component->instance())->generateShareText();

    expect($shareText)->toContain('Sunday Funday')
        ->toContain('on Zorbl')
        ->toContain('15x15')
        ->toContain('3:45')
        ->toContain(route('puzzles.solve', $crossword->id));
});

test('generateShareText formats hours correctly for long solves', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Marathon Puzzle',
        'width' => 21,
        'height' => 21,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 3661,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $shareText = invade($component->instance())->generateShareText();

    expect($shareText)->toContain('1:01:01')
        ->toContain('21x21');
});

test('share results banner is visible when puzzle is solved', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Completed Puzzle',
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 90,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('You solved it!')
        ->assertSee('Share Results');
});

test('share results banner is not visible when puzzle is not solved', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('You solved it!')
        ->assertDontSee('Share Results');
});

test('share text uses public puzzle route for sharing', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create([
        'title' => 'Public Puzzle',
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 60,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $shareText = invade($component->instance())->generateShareText();

    expect($shareText)->toContain('/puzzles/')
        ->not->toContain('/crosswords/');
});

test('formatSolveTime handles zero seconds', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $result = invade($component->instance())->formatSolveTime(0);

    expect($result)->toBe('0:00');
});

test('share banner shows solve time and grid size in completion section', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Timed Puzzle',
        'width' => 5,
        'height' => 5,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 185,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('3:05')
        ->assertSeeHtml('5&times;5');
});

test('generateShareText is callable as a Livewire action', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Callable Test',
        'width' => 10,
        'height' => 10,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $shareText = invade($component->instance())->generateShareText();

    expect($shareText)->toBeString()
        ->toContain('Callable Test')
        ->toContain('10x10')
        ->toContain('2:00');
});

test('share text contains three lines', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'title' => 'Three Lines Test',
        'width' => 7,
        'height' => 7,
    ]);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 42,
    ]);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $shareText = invade($component->instance())->generateShareText();
    $lines = explode("\n", $shareText);

    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toContain('Three Lines Test')
        ->and($lines[1])->toContain('7x7')
        ->and($lines[1])->toContain('0:42')
        ->and($lines[2])->toContain('/puzzles/');
});
