<?php

use App\Enums\PdfLayout;
use App\Models\Crossword;
use App\Models\User;
use App\Services\PdfWrapLayout;
use Livewire\Livewire;

test('a cleared constructor name and copyright stay empty when the editor is reopened', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    $crossword = Crossword::factory()->for($user)->create(['author' => 'Alice', 'copyright' => '© 2026 Alice']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('author', '')
        ->set('copyright', '')
        ->call('saveMetadata');

    expect($crossword->refresh()->author)->toBeEmpty()
        ->and($crossword->copyright)->toBeEmpty();

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('author', '')
        ->assertSet('copyright', '');
});

test('the solver shows the byline only for a puzzle with a constructor name', function (?string $author, string $expectedByline) {
    $constructor = User::factory()->create(['name' => 'Connie Structor']);
    $crossword = Crossword::factory()->for($constructor)->published()->create(['author' => $author]);

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('authorName', $expectedByline);
})->with([
    'name given' => ['Connie Structor', 'Connie Structor'],
    'name cleared' => ['', ''],
    'name never set' => [null, ''],
]);

test('the guest solve page leaves the byline out for a puzzle without a constructor name', function () {
    $constructor = User::factory()->create(['name' => 'Connie Structor']);
    $withName = Crossword::factory()->for($constructor)->published()->create(['author' => 'Connie Structor']);
    $withoutName = Crossword::factory()->for($constructor)->published()->create(['author' => '']);

    $this->get(route('puzzles.solve', $withName))->assertOk()->assertSee('Connie Structor');
    $this->get(route('puzzles.solve', $withoutName))->assertOk()->assertDontSee('Connie Structor');
});

test('the PDF leaves out the byline and copyright line when both are empty', function () {
    $html = view('exports.crossword-pdf', [
        'title' => 'Anonymous Puzzle',
        'author' => '',
        'copyright' => '',
        'notes' => null,
        'numberedGrid' => [[1]],
        'solution' => [['A']],
        'prefilled' => null,
        'cluesAcross' => [['number' => 1, 'clue' => 'First letter']],
        'cluesDown' => [['number' => 1, 'clue' => 'Top grade']],
        'styles' => [],
        'includeSolution' => false,
        'cellSize' => 0.33,
        'numberFontSize' => 6,
        'letterFontSize' => 9,
        'numberHeight' => 0.116,
    ])->render();

    $plan = app(PdfWrapLayout::class)->plan(
        PdfLayout::GridTopLeft, 504.0, 648.0, 1, 1,
        ['title' => 'Anonymous Puzzle', 'author' => '', 'copyright' => ''],
        [['number' => 1, 'clue' => 'First letter']],
        [['number' => 1, 'clue' => 'Top grade']],
    );

    expect($html)->not->toContain('class="meta"')
        ->and(array_column($plan['header'], 'type'))->not->toContain('meta');
});
