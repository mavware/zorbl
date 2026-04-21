<?php

use App\Models\Crossword;
use App\Models\Template;
use App\Models\User;
use App\Services\GridTemplateProvider;
use Database\Factories\TemplateFactory;
use Illuminate\Support\Facades\Cache;

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

test('active admin templates appear first in getTemplates', function () {
    Cache::flush();

    Template::factory()->square(15)->create([
        'name' => 'Admin Curated',
        'grid' => TemplateFactory::openGrid(15, 15),
        'sort_order' => 0,
    ]);

    $templates = app(GridTemplateProvider::class)->getTemplates(15, 15);

    expect($templates[0]['name'])->toBe('Admin Curated');
});

test('inactive admin templates are excluded from getTemplates', function () {
    Cache::flush();

    Template::factory()->square(15)->inactive()->create([
        'name' => 'Hidden Template',
        'grid' => TemplateFactory::openGrid(15, 15),
    ]);

    $templates = app(GridTemplateProvider::class)->getTemplates(15, 15);

    expect(collect($templates)->pluck('name'))->not->toContain('Hidden Template');
});

test('admin templates respect sort_order', function () {
    Cache::flush();

    Template::factory()->square(15)->create([
        'name' => 'Second',
        'grid' => TemplateFactory::openGrid(15, 15),
        'sort_order' => 10,
    ]);
    Template::factory()->square(15)->create([
        'name' => 'First',
        'grid' => TemplateFactory::openGrid(15, 15),
        'sort_order' => 1,
    ]);

    $templates = app(GridTemplateProvider::class)->getTemplates(15, 15);
    $names = array_column($templates, 'name');
    $firstIndex = array_search('First', $names, true);
    $secondIndex = array_search('Second', $names, true);

    expect($firstIndex)->toBeLessThan($secondIndex);
});
