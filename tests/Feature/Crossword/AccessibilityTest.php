<?php

use App\Models\Crossword;
use App\Models\User;

test('solver page has grid with aria role and label', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solver', $crossword))
        ->assertOk()
        ->assertSee('role="grid"', false)
        ->assertSee('role="gridcell"', false);
});

test('solver page has skip-to-grid link', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solver', $crossword))
        ->assertOk()
        ->assertSee('Skip to crossword grid');
});

test('solver page has screen reader live region', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solver', $crossword))
        ->assertOk()
        ->assertSee('aria-live="polite"', false);
});

test('grid container has keyboard tabindex', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.solver', $crossword))
        ->assertOk()
        ->assertSee('id="crossword-grid"', false)
        ->assertSee('tabindex="0"', false);
});
