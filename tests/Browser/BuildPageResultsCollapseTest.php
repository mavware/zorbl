<?php

use App\Models\Crossword;
use App\Models\User;

it('collapses the results grid to one row and expands on demand', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->count(12)->create();

    $this->actingAs($user);

    $page = visit(route('crosswords.index'))
        ->assertSee('of 12 puzzles')
        ->assertVisible('[data-test="toggle-all-puzzles-button"]')
        ->assertNoJavaScriptErrors();

    $page->click('Show all puzzles')
        ->assertSee('Showing all 12 puzzles')
        ->assertSee('Show fewer');
});
