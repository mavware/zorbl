<?php

use App\Enums\CrosswordLayout;
use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

test('the solver shows a puzzle in the corner layout its builder picked', function (CrosswordLayout $layout) {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create(['layout' => $layout]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('cornerLayout', $layout)
        ->assertSeeHtml('data-corner-layout="'.$layout->name.'"');
})->with([
    CrosswordLayout::CornerGridTopLeft,
    CrosswordLayout::CornerGridTopRight,
    CrosswordLayout::CornerGridBottomLeft,
    CrosswordLayout::CornerGridBottomRight,
]);

test('the solver keeps its standard arrangement for a puzzle without a corner layout', function (?CrosswordLayout $layout) {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create(['layout' => $layout]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('cornerLayout', null)
        ->assertDontSeeHtml('data-test="corner-layout"');
})->with([
    'no layout picked' => [null],
    'an editor-only layout' => [CrosswordLayout::TabbedCluesLeft],
]);

test('the guest solve page shows a puzzle in the corner layout its builder picked', function () {
    $crossword = Crossword::factory()->for(User::factory())->published()->create(['layout' => CrosswordLayout::CornerGridBottomRight]);

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('data-corner-layout="CornerGridBottomRight"', false);
});

test('the guest solve page keeps its standard arrangement for a puzzle without a corner layout', function () {
    $crossword = Crossword::factory()->for(User::factory())->published()->create(['layout' => null]);

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertDontSee('data-test="corner-layout"', false);
});
