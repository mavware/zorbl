<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('celebration modal includes share results button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Share Results');
});

test('toolbar shows share button when puzzle is solved', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create(['width' => 3, 'height' => 3]);

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
        'progress' => $crossword->solution,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('isSolved', true)
        ->assertSeeHtml('shareResults()');
});

test('share button is visible on puzzle completion', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    $component->call('saveProgress', $crossword->solution, true, 120);

    $component->assertSet('isSolved', true)
        ->assertSeeHtml('Share Results');
});

test('guest solver shows share button markup', function () {
    $crossword = Crossword::factory()->published()->create();

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('Share results');
});

test('celebration modal has share as primary action', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create();

    $this->actingAs($user);

    $html = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->html();

    // Share Results button should appear before Browse More Puzzles in the celebration modal
    $sharePos = strpos($html, 'Share Results');
    $browsePos = strpos($html, 'Browse More Puzzles');

    expect($sharePos)->toBeLessThan($browsePos);
});

test('solver page passes puzzle title to template for share text', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create([
        'title' => 'My Test Crossword',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('title', 'My Test Crossword')
        ->assertSeeHtml('data-puzzle-title');
});
