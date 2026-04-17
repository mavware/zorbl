<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;

it('anyone signed in can load a published puzzle solver', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($owner)
        ->published()
        ->withBlocks()
        ->withSolution()
        ->create(['title' => 'Public Grid']);

    $this->actingAs($solver);

    visit(route('crosswords.solver', $crossword))
        ->assertSee('Across')
        ->assertSee('Down')
        ->assertPresent('#crossword-grid')
        ->assertNoJavaScriptErrors();
});

it('creates a puzzle attempt when a solver visits the page', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($owner)
        ->published()
        ->withBlocks()
        ->withSolution()
        ->create();

    $this->actingAs($solver);

    expect(PuzzleAttempt::where('user_id', $solver->id)
        ->where('crossword_id', $crossword->id)
        ->exists())->toBeFalse();

    visit(route('crosswords.solver', $crossword))
        ->assertNoJavaScriptErrors();

    expect(PuzzleAttempt::where('user_id', $solver->id)
        ->where('crossword_id', $crossword->id)
        ->exists())->toBeTrue();
});

it('does not leak solution letters into the solver DOM', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($owner)
        ->published()
        ->withBlocks()
        ->withSolution()
        ->create();

    $this->actingAs($solver);

    $letters = collect($crossword->solution)
        ->flatten()
        ->filter(fn ($v) => is_string($v) && $v !== '' && $v !== '#')
        ->unique()
        ->take(3)
        ->values()
        ->all();

    $page = visit(route('crosswords.solver', $crossword))
        ->assertNoJavaScriptErrors();

    foreach ($letters as $letter) {
        $page->assertDontSeeIn('#crossword-grid', $letter);
    }
});

it('non-owners cannot solve an unpublished puzzle', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->withBlocks()->create([
        'is_published' => false,
    ]);

    $this->actingAs($intruder);

    $this->get(route('crosswords.solver', $crossword))->assertForbidden();
});
