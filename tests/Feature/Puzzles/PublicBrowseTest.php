<?php

use App\Models\Crossword;
use App\Models\User;

test('browse page loads without authentication', function () {
    Crossword::factory()->published()->create(['title' => 'Public Puzzle']);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Browse Puzzles')
        ->assertSee('Public Puzzle');
});

test('browse page shows only published puzzles', function () {
    Crossword::factory()->published()->create(['title' => 'Visible Puzzle']);
    Crossword::factory()->create(['title' => 'Draft Puzzle', 'is_published' => false]);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Visible Puzzle')
        ->assertDontSee('Draft Puzzle');
});

test('browse page shows puzzle metadata', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Metadata Test',
        'width' => 15,
        'height' => 15,
    ]);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Metadata Test')
        ->assertSee('15&times;15', false);
});

test('browse page shows try this puzzle button for guests', function () {
    Crossword::factory()->published()->create();

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Try This Puzzle');
});

test('browse page shows start solving button for authenticated users', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->create();

    $this->actingAs($user)
        ->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Start Solving');
});
