<?php

use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('solver page passes share title and URL to Alpine component', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create([
        'title' => 'My Awesome Puzzle',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSeeHtml('shareTitle:')
        ->assertSeeHtml('shareUrl:')
        ->assertSeeHtml('Share Results')
        ->assertSeeHtml('Share');
});

test('solved puzzle shows share button in toolbar', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSeeHtml('shareResults()');
});

test('guest solver page passes share data', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Guest Puzzle',
    ]);

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSeeHtml('shareTitle:')
        ->assertSeeHtml('shareUrl:');
});
