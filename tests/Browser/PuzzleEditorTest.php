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
        ->assertPresent('[data-test="settings-pane"]')
        ->assertNoJavaScriptErrors();
});

it('shows the existing title in the settings sidebar', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withBlocks()
        ->create(['title' => 'My Sunday Grid']);

    $this->actingAs($user);

    visit(route('crosswords.editor', $crossword))
        ->assertVisible('[data-test="settings-pane"] input[placeholder="Puzzle title"]')
        ->assertValue('[data-test="settings-pane"] input[placeholder="Puzzle title"]', 'My Sunday Grid')
        ->assertNoJavaScriptErrors();
});

it('non-owners cannot view the editor', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->withBlocks()->create();

    $this->actingAs($intruder);

    $this->get(route('crosswords.editor', $crossword))->assertForbidden();
});

it('clears cell selection when clicking outside the grid and clue panels', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withBlocks()
        ->create(['title' => 'Click Away Test']);

    $this->actingAs($user);

    $page = visit(route('crosswords.editor', $crossword));

    $gridData = 'Alpine.$data(document.querySelector(\'[x-data^="crosswordGrid"]\'))';

    $page->script("(() => { const d = {$gridData}; d.selectedRow = 0; d.selectedCol = 0; })()");
    $page->assertScript("{$gridData}.selectedRow", 0);

    $page->click('[data-test="settings-pane"] [data-test="settings-tabs"]');

    $page->assertScript("{$gridData}.selectedRow", -1);
    $page->assertScript("{$gridData}.selectedCol", -1);
    $page->assertNoJavaScriptErrors();
});

it('keeps cell selection when clicking inside a clue panel', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withBlocks()
        ->create(['title' => 'Clue Panel Test']);

    $this->actingAs($user);

    $page = visit(route('crosswords.editor', $crossword));

    $gridData = 'Alpine.$data(document.querySelector(\'[x-data^="crosswordGrid"]\'))';

    $page->script("(() => { const d = {$gridData}; d.selectedRow = 0; d.selectedCol = 0; })()");

    $page->script("document.querySelector('[x-ref=\"acrossPanel\"]').dispatchEvent(new MouseEvent('mousedown', {bubbles: true}))");

    $page->assertScript("{$gridData}.selectedRow", 0);
    $page->assertScript("{$gridData}.selectedCol", 0);
    $page->assertNoJavaScriptErrors();
});
