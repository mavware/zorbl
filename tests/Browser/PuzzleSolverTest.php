<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use CrosswordBuilder\CrosswordIO\GridNumberer;

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

it('renders the grid with the expected ARIA attributes for screen readers', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($owner)
        ->published()
        ->withBlocks()
        ->withSolution()
        ->create();

    $this->actingAs($solver);

    visit(route('crosswords.solver', $crossword))
        ->assertNoJavaScriptErrors()
        ->assertPresent('#crossword-grid[role=grid]')
        ->assertPresent('#crossword-grid[aria-rowcount]')
        ->assertPresent('#crossword-grid[aria-colcount]')
        ->assertPresent('#crossword-grid[aria-keyshortcuts]')
        ->assertPresent('.crossword-cell[role=gridcell]')
        ->assertPresent('#crossword-cell-0-0')
        ->assertPresent('.crossword-cell[aria-rowindex="1"]')
        ->assertPresent('.crossword-cell[aria-colindex="1"]')
        ->assertPresent('[aria-live="polite"]');
});

it('switches the mobile clue tab and scrolls to the active clue as the grid selection moves', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($owner)
        ->published()
        ->withBlocks()
        ->withSolution()
        ->create();

    $numbered = app(GridNumberer::class)->number($crossword->grid, $crossword->width, $crossword->height);

    $crossword->update([
        'grid' => $numbered['grid'],
        'clues_across' => collect($numbered['across'])->map(fn (array $slot) => ['number' => $slot['number'], 'clue' => "Across clue {$slot['number']}"])->values()->all(),
        'clues_down' => collect($numbered['down'])->map(fn (array $slot) => ['number' => $slot['number'], 'clue' => "Down clue {$slot['number']}"])->values()->all(),
    ]);

    $lastNumber = collect($numbered['across'])->max('number');

    $this->actingAs($solver);

    $solverData = 'Alpine.$data(document.querySelector(\'[x-data^="crosswordSolver"]\'))';

    $page = visit(route('crosswords.solver', $crossword))->on()->mobile();

    $page->assertNoJavaScriptErrors()
        ->click('#crossword-cell-0-0')
        ->assertScript("{$solverData}.mobileClueTab", 'across')
        ->assertPresent('#mobile-clue-across-1.bg-blue-100')
        ->assertNotPresent('#mobile-clue-down-1');

    $page->keys('#crossword-grid', ['ArrowDown'])
        ->assertScript("{$solverData}.direction", 'down')
        ->assertScript("{$solverData}.mobileClueTab", 'down')
        ->assertPresent('#mobile-clue-down-1.bg-blue-100')
        ->assertNotPresent('#mobile-clue-across-1');

    $page->keys('#crossword-grid', ['ArrowRight'])
        ->assertScript("{$solverData}.mobileClueTab", 'across')
        ->assertPresent('#mobile-clue-across-1.bg-blue-100');

    // Jump to the last across clue: the tab stays on Across and the list
    // scrolls so the highlighted clue is visible inside the panel.
    $page->script("{$solverData}.selectClue('across', {$lastNumber})");

    $page->assertScript("{$solverData}.mobileClueTab", 'across')
        ->assertPresent("#mobile-clue-across-{$lastNumber}.bg-blue-100")
        ->assertScript("document.querySelector('[x-ref=\"mobileCluePanel\"]').scrollTop > 0")
        ->assertScript("(() => {
            const panel = document.querySelector('[x-ref=\"mobileCluePanel\"]').getBoundingClientRect();
            const clue = document.getElementById('mobile-clue-across-{$lastNumber}').getBoundingClientRect();
            return clue.top >= panel.top && clue.bottom <= panel.bottom;
        })()")
        ->assertNoJavaScriptErrors();
});
