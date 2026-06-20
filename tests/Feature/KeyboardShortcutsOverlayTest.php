<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('solver page renders the keyboard shortcuts help button', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Keyboard shortcuts')
        ->assertSeeHtml('Keyboard shortcuts (?)');
});

test('solver page contains the shortcuts modal markup', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Keyboard Shortcuts')
        ->assertSeeHtml('showShortcuts');
});

test('shortcuts modal includes navigation shortcuts', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Move between cells')
        ->assertSeeHtml('Jump to next clue')
        ->assertSeeHtml('Jump to previous clue')
        ->assertSeeHtml('Toggle direction (Across/Down)')
        ->assertSeeHtml('Deselect cell');
});

test('shortcuts modal includes input shortcuts', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Type a letter')
        ->assertSeeHtml('Delete letter and move back')
        ->assertSeeHtml('Clear current cell')
        ->assertSeeHtml('Toggle pencil mode')
        ->assertSeeHtml('Toggle rebus mode (multi-letter)');
});

test('shortcuts modal includes undo/redo shortcuts', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Undo')
        ->assertSeeHtml('Redo');
});

test('shortcuts modal includes the toggle hint in footer', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create([
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Press ? to toggle this overlay');
});

test('solver shortcut component renders key and description', function () {
    $view = $this->blade(
        '<x-solver-shortcut keys="Ctrl + Z" description="Undo" />'
    );

    $view->assertSeeHtml('Undo');
    $view->assertSee('Ctrl');
    $view->assertSee('Z');
});

test('solver shortcut component renders multi-key combinations with separators', function () {
    $view = $this->blade(
        '<x-solver-shortcut keys="Ctrl + Shift + Z" description="Redo" />'
    );

    $view->assertSee('Ctrl');
    $view->assertSee('Shift');
    $view->assertSee('Z');
});

test('solver shortcut component renders single keys', function () {
    $view = $this->blade(
        '<x-solver-shortcut keys="Escape" description="Deselect cell" />'
    );

    $view->assertSee('Escape');
    $view->assertSee('Deselect cell');
});
