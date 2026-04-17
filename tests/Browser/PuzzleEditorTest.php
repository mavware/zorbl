<?php

use App\Models\Crossword;
use App\Models\User;

it('owner can load the editor and sees the toolbar, grid, and clue panels', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withBlocks()
        ->create(['title' => 'Test Puzzle']);

    $this->actingAs($user);

    visit(route('crosswords.editor', $crossword))
        ->assertSee('Across')
        ->assertSee('Down')
        ->assertPresent('[role="grid"]')
        ->assertPresent('input[placeholder="Puzzle title"][wire\\:change="saveMetadata"]')
        ->assertNoJavaScriptErrors();
});

it('shows the existing title in the toolbar', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withBlocks()
        ->create(['title' => 'My Sunday Grid']);

    $this->actingAs($user);

    visit(route('crosswords.editor', $crossword))
        ->assertValue('input[placeholder="Puzzle title"][wire\\:change="saveMetadata"]', 'My Sunday Grid')
        ->assertNoJavaScriptErrors();
});

it('non-owners cannot view the editor', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->withBlocks()->create();

    $this->actingAs($intruder);

    $this->get(route('crosswords.editor', $crossword))->assertForbidden();
});
