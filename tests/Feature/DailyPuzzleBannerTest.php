<?php

use App\Models\Crossword;
use App\Models\User;

test('daily puzzle banner invites an unsolved viewer to solve', function () {
    $author = User::factory()->create(['name' => 'Ada Setter']);
    $crossword = Crossword::factory()->published()->for($author)->create(['title' => 'Morning Grid']);

    $view = $this->blade(
        '<x-daily-puzzle-banner :crossword="$crossword" :solved="false" />',
        ['crossword' => $crossword]
    );

    $view->assertSee('Puzzle of the Day')
        ->assertSee('Morning Grid')
        ->assertSee('by Ada Setter')
        ->assertSee('Solve Today\'s Puzzle')
        ->assertSee(route('crosswords.solver', $crossword), false)
        ->assertSee(route('puzzles.daily-history'), false)
        ->assertDontSee('View Solution')
        ->assertDontSee('Solved');
});

test('daily puzzle banner shows the solved state with a link to the solution', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Morning Grid']);

    $view = $this->blade(
        '<x-daily-puzzle-banner :crossword="$crossword" :solved="true" />',
        ['crossword' => $crossword]
    );

    $view->assertSee('Solved')
        ->assertSee('View Solution')
        ->assertDontSee('Solve Today\'s Puzzle');
});
