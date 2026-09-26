<?php

use App\Filament\Widgets\Pulse\ExceptionsWidget;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

test('admin navigation links to the word list json redirect in a new tab', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertSuccessful()
        ->assertSee('Word List JSON')
        ->assertSee('href="'.route('filament.admin.word-list').'"', false);
});

test('word list link redirects admins to the json manifest', function () {
    Storage::fake('s3');
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.word-list'))
        ->assertRedirect(Storage::disk('s3')->url('exports/words/manifest.json'));
});

test('word list link is forbidden to non-admins', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('filament.admin.word-list'))
        ->assertForbidden();
});
