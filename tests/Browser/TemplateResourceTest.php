<?php

use App\Models\User;
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
