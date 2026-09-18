<?php

use App\Models\Crossword;
use App\Models\User;
use CrosswordBuilder\CrosswordIO\GridNumberer;

it('debug keys', function () {
    $owner = User::factory()->create();
    $solver = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->withBlocks()->withSolution()->create();
    $numbered = app(GridNumberer::class)->number($crossword->grid, $crossword->width, $crossword->height);
    $crossword->update([
        'grid' => $numbered['grid'],
        'clues_across' => collect($numbered['across'])->map(fn (array $slot) => ['number' => $slot['number'], 'clue' => "A {$slot['number']}"])->values()->all(),
        'clues_down' => collect($numbered['down'])->map(fn (array $slot) => ['number' => $slot['number'], 'clue' => "D {$slot['number']}"])->values()->all(),
    ]);
    $this->actingAs($solver);
    $d = 'Alpine.$data(document.querySelector(\'[x-data^="crosswordSolver"]\'))';
    $page = visit(route('crosswords.solver', $crossword))->on()->mobile();
    $page->wait(1);
    $page->click('#crossword-cell-0-0');
    $page->wait(0.5);
    dump('after click active=' . $page->script('document.activeElement.id') . ' sel=' . $page->script("{$d}.selectedRow + ',' + {$d}.selectedCol"));
    $page->keys('#crossword-grid', ['ArrowDown']);
    dump('after keys active=' . $page->script('document.activeElement.id') . ' sel=' . $page->script("{$d}.selectedRow + ',' + {$d}.selectedCol") . ' dir=' . $page->script("{$d}.direction"));
    $page->script("document.getElementById('crossword-grid').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowDown', bubbles: true}))");
    dump('after dispatch sel=' . $page->script("{$d}.selectedRow + ',' + {$d}.selectedCol") . ' dir=' . $page->script("{$d}.direction") . ' tab=' . $page->script("{$d}.mobileClueTab"));
});
