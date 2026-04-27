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

it('toggles only the clicked cell (and its mirror) across multiple clicks', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/admin/templates/create');

    // Click (0, 2) — its mirror is (14, 12)
    $page->script('document.querySelector(\'[data-testid="template-grid-editor"] button[data-row="0"][data-col="2"]\').click();');

    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="0"][data-col="2"]', 'data-block', 'true')
        ->assertAttribute('[data-testid="template-grid-editor"] button[data-row="14"][data-col="12"]', 'data-block', 'true');

    // Cells the user did not click must remain open. Spot-check a handful.
    foreach ([[3, 7], [5, 1], [9, 9], [12, 4]] as [$r, $c]) {
        $page->assertAttribute(
            "[data-testid=\"template-grid-editor\"] button[data-row=\"{$r}\"][data-col=\"{$c}\"]",
            'data-block',
            'false',
        );
    }

    // Click (3, 7) — its mirror is (11, 7)
    $page->script('document.querySelector(\'[data-testid="template-grid-editor"] button[data-row="3"][data-col="7"]\').click();');

    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="3"][data-col="7"]', 'data-block', 'true')
        ->assertAttribute('[data-testid="template-grid-editor"] button[data-row="11"][data-col="7"]', 'data-block', 'true');

    // Previous click should still hold.
    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="0"][data-col="2"]', 'data-block', 'true')
        ->assertAttribute('[data-testid="template-grid-editor"] button[data-row="14"][data-col="12"]', 'data-block', 'true');

    $page->assertNoJavaScriptErrors();
});

it('toggles only the clicked cell on an existing template via real click', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $template = Template::factory()->square(15)->create([
        'styles' => null,
    ]);

    $page = visit("/admin/templates/{$template->id}/edit");

    $page->click('[data-testid="template-grid-editor"] button[data-row="2"][data-col="3"]');

    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="2"][data-col="3"]', 'data-block', 'true')
        ->assertAttribute('[data-testid="template-grid-editor"] button[data-row="12"][data-col="11"]', 'data-block', 'true');

    foreach ([[2, 4], [3, 3], [1, 3], [2, 2]] as [$r, $c]) {
        $page->assertAttribute(
            "[data-testid=\"template-grid-editor\"] button[data-row=\"{$r}\"][data-col=\"{$c}\"]",
            'data-block',
            'false',
        );
    }

    $page->assertNoJavaScriptErrors();
});

it('reflects each click in the DOM before the next click fires (no lag-by-one)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $template = Template::factory()->create([
        'width' => 5,
        'height' => 5,
        'grid' => array_fill(0, 5, array_fill(0, 5, 0)),
        'styles' => null,
    ]);

    $page = visit("/admin/templates/{$template->id}/edit");

    // Verify the DOM updates between clicks. Each assertion runs before the next click,
    // so a lag-by-one render would fail the immediate assertion.
    $page->click('[data-testid="template-grid-editor"] button[data-row="1"][data-col="1"]');
    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="1"][data-col="1"]', 'data-block', 'true');

    $page->click('[data-testid="template-grid-editor"] button[data-row="2"][data-col="2"]');
    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="2"][data-col="2"]', 'data-block', 'true');

    $page->click('[data-testid="template-grid-editor"] button[data-row="0"][data-col="3"]');
    $page->assertAttribute('[data-testid="template-grid-editor"] button[data-row="0"][data-col="3"]', 'data-block', 'true');

    $page->assertNoJavaScriptErrors();
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
