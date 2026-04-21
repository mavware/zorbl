<?php

use App\Models\Template;
use App\Models\User;
use Database\Seeders\ProceduralTemplateSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
});

it('renders the interactive grid editor on the template create page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    visit('/admin/templates/create')
        ->assertPresent('[data-testid="template-grid-editor"]')
        ->assertPresent('[data-testid="template-grid-editor"] button[data-row="0"][data-col="0"]')
        ->assertNoJavaScriptErrors();
});

it('renders the interactive grid editor on the template edit page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $template = Template::factory()->square(15)->create();

    visit("/admin/templates/{$template->id}/edit")
        ->assertPresent('[data-testid="template-grid-editor"]')
        ->assertPresent('[data-testid="template-grid-editor"] button[data-row="0"][data-col="0"]')
        ->assertNoJavaScriptErrors();
});

it('renders the grid editor for a seeded procedural template', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $this->seed(ProceduralTemplateSeeder::class);

    $template = Template::where('width', 15)->where('name', 'Cross')->firstOrFail();

    visit("/admin/templates/{$template->id}/edit")
        ->assertPresent('[data-testid="template-grid-editor"]')
        ->assertPresent('[data-testid="template-grid-editor"] button[data-row="0"][data-col="0"]')
        ->assertPresent('[data-testid="template-grid-editor"] button[data-row="14"][data-col="14"]')
        ->assertNoJavaScriptErrors();
});

it('renders the grid with visible cell dimensions (no Tailwind utility dependencies)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $this->seed(ProceduralTemplateSeeder::class);
    $template = Template::where('width', 5)->where('name', 'Corner')->firstOrFail();

    $page = visit("/admin/templates/{$template->id}/edit");

    $width = $page->script('(() => { const grid = document.querySelector(\'[data-grid-container]\'); return Math.round(grid.getBoundingClientRect().width); })()');
    $height = $page->script('(() => { const grid = document.querySelector(\'[data-grid-container]\'); return Math.round(grid.getBoundingClientRect().height); })()');
    $display = $page->script('(() => { const grid = document.querySelector(\'[data-grid-container]\'); return getComputedStyle(grid).display; })()');

    expect($display)->toBe('grid')
        ->and($width)->toBeGreaterThan(100)
        ->and($height)->toBeGreaterThan(100);
});

it('toggles a block and its rotational mirror when a cell is clicked', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $page = visit('/admin/templates/create');

    $page->script('document.querySelector(\'[data-testid="template-grid-editor"] button[data-row="0"][data-col="0"]\').click();');

    $page->assertAttribute(
        '[data-testid="template-grid-editor"] button[data-row="0"][data-col="0"]',
        'data-block',
        'true',
    );

    $page->assertAttribute(
        '[data-testid="template-grid-editor"] button[data-row="14"][data-col="14"]',
        'data-block',
        'true',
    );

    $page->assertNoJavaScriptErrors();
});
