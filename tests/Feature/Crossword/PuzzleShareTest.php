<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('solver celebration modal contains share buttons', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Weekend Fun',
        'width' => 5,
        'height' => 5,
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('copyShareText')
        ->assertSeeHtml('shareResult')
        ->assertSeeHtml('twitterShareUrl');
});

test('solver passes puzzle title to Alpine component', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'My Great Puzzle',
        'width' => 5,
        'height' => 5,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('puzzleTitle')
        ->assertSeeHtml('My Great Puzzle');
});

test('solver celebration modal links to public puzzle URL', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 5,
        'height' => 5,
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create();

    $puzzleUrl = route('puzzles.solve', $crossword);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml($puzzleUrl);
});

test('guest solver page contains share buttons', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Guest Puzzle',
        'width' => 5,
        'height' => 5,
    ]);

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('Copy Result')
        ->assertSee('puzzleTitle');
});

test('guest solver page links to correct puzzle URL for sharing', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Shareable Puzzle',
        'width' => 5,
        'height' => 5,
    ]);

    $puzzleUrl = route('puzzles.solve', $crossword);

    $this->get($puzzleUrl)
        ->assertOk()
        ->assertSee($puzzleUrl);
});
