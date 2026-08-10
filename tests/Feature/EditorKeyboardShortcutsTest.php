<?php

use App\Models\Crossword;
use App\Models\User;

test('editor page renders the keyboard shortcuts help button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeHtml('Keyboard shortcuts (?)')
        ->assertSeeHtml('aria-label="Keyboard shortcuts"');
});

test('editor page contains the shortcuts modal markup', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeHtml('Keyboard Shortcuts')
        ->assertSeeHtml('showShortcuts');
});

test('editor shortcuts modal includes navigation shortcuts', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeHtml('Move between cells')
        ->assertSeeHtml('Jump to next clue')
        ->assertSeeHtml('Jump to previous clue')
        ->assertSeeHtml('Toggle direction (Across/Down)')
        ->assertSeeHtml('Deselect cell');
});

test('editor shortcuts modal includes grid editing shortcuts', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeHtml('Toggle black square')
        ->assertSeeHtml('Type a letter')
        ->assertSeeHtml('Delete letter and move back')
        ->assertSeeHtml('Clear current cell')
        ->assertSeeHtml('Toggle rebus mode (multi-letter)');
});

test('editor shortcuts modal includes the toggle hint in footer', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeHtml('Press ? to toggle this overlay');
});

test('editor grid javascript initializes showShortcuts state', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSeeHtml('showShortcuts');
});
