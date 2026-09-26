<?php

use App\Filament\Widgets\Pulse\ExceptionsWidget;
use App\Models\User;
use Spatie\Permission\Models\Role;

test('admin can access dashboard', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertSuccessful();
});

test('non-admin cannot access dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertForbidden();
});

test('guest is redirected from dashboard', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect();
});

test('dashboard shows the pulse exceptions card', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSeeLivewire(ExceptionsWidget::class)
        ->assertSee('@scope (.fi-page-content)', false);
});

test('admin navigation links to nightwatch in a new tab', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    config(['services.nightwatch.dashboard_url' => 'https://nightwatch.laravel.com/example-app']);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSee('Nightwatch')
        ->assertSee('href="https://nightwatch.laravel.com/example-app"', false)
        ->assertSee('target="_blank"', false);
});

test('admin navigation links to the word list json manifest in a new tab', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSee('Word List JSON')
        ->assertSee('href="'.route('api.v1.words.manifest').'"', false);
});
