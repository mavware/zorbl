<?php

use App\Models\Crossword;
use App\Models\User;
use App\Services\GridTemplateProvider;

test('template picker appears in new puzzle modal for standard sizes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $templates = app(GridTemplateProvider::class)->getTemplates(15, 15);
    $firstTemplateName = $templates[0]['name'];

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('showNewModal', true)
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->assertSee('Grid Template')
        ->assertSee($firstTemplateName);
});

test('template picker does not appear for non-standard sizes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('showNewModal', true)
        ->set('newWidth', 2)
        ->set('newHeight', 2)
        ->assertDontSee('Grid Template');
});

test('creating puzzle with selected template uses that grid', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $templates = app(GridTemplateProvider::class)->getTemplates(15, 15);
    $expectedBlocks = [];

    foreach ($templates[0]['grid'] as $r => $row) {
        foreach ($row as $c => $cell) {
            if ($cell === '#') {
                $expectedBlocks[] = [$r, $c];
            }
        }
    }

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->set('selectedTemplate', 0)
        ->call('createPuzzle');

    $crossword = Crossword::latest()->first();
    expect($crossword->width)->toBe(15)
        ->and($crossword->height)->toBe(15);

    // Verify the template blocks are present in the created grid
    foreach ($expectedBlocks as [$r, $c]) {
        expect($crossword->grid[$r][$c])->toBe('#', "Block expected at ($r, $c)");
    }
});

test('creating puzzle without template uses blank grid', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('newWidth', 5)
        ->set('newHeight', 5)
        ->set('selectedTemplate', null)
        ->call('createPuzzle');

    $crossword = Crossword::latest()->first();

    // All cells should be fillable (no blocks)
    foreach ($crossword->grid as $row) {
        foreach ($row as $cell) {
            expect($cell)->not->toBe('#');
        }
    }
});

test('changing dimensions resets template selection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->set('selectedTemplate', 2)
        ->set('newWidth', 13)
        ->assertSet('selectedTemplate', null);
});

test('changing height resets template selection', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.index')
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->set('selectedTemplate', 1)
        ->set('newHeight', 11)
        ->assertSet('selectedTemplate', null);
});
