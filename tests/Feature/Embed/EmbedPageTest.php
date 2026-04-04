<?php

use App\Models\Crossword;

test('embed page loads for published crossword', function () {
    $crossword = Crossword::factory()->published()->create(['title' => 'Embed Page Test']);

    $this->get(route('embed.solver', $crossword))
        ->assertOk()
        ->assertSee('data-zorbl-embed')
        ->assertSee('Powered by Zorbl');
});

test('embed page returns 404 for unpublished crossword', function () {
    $crossword = Crossword::factory()->create(['is_published' => false]);

    $this->get(route('embed.solver', $crossword))
        ->assertNotFound();
});

test('embed page loads without authentication', function () {
    $crossword = Crossword::factory()->published()->create();

    // No actingAs — unauthenticated request
    $this->get(route('embed.solver', $crossword))
        ->assertOk();
});

test('embed page includes crossword id in data attribute', function () {
    $crossword = Crossword::factory()->published()->create();

    $this->get(route('embed.solver', $crossword))
        ->assertOk()
        ->assertSee('data-crossword-id="'.$crossword->id.'"', false);
});
