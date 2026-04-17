<?php

use App\Models\Crossword;
use App\Models\User;

it('renders the puzzle editor without JavaScript errors', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->withBlocks()->create();

    $this->actingAs($user);

    $page = visit(route('crosswords.editor', $crossword));

    $page->assertSee('Across')
        ->assertSee('Down')
        ->assertPresent('[role="grid"]')
        ->assertNoJavaScriptErrors();
});

it('renders the puzzle solver without JavaScript errors', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($owner)
        ->published()
        ->withBlocks()
        ->withSolution()
        ->create();

    $this->actingAs($solver);

    $page = visit(route('crosswords.solver', $crossword));

    $page->assertSee('Across')
        ->assertSee('Down')
        ->assertPresent('[role="grid"]')
        ->assertNoJavaScriptErrors();
});
