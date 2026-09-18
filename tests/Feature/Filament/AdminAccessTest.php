<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('non-admin users cannot access admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('guest users are redirected to login', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

test('admin users can access admin panel', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $response = $this->actingAs($admin)->get('/admin');

    expect($response->getStatusCode())->not->toBe(403)
        ->and($response->getStatusCode())->not->toBe(401);
});

test('admin panel shows the admin/user switcher linking to both homepages', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->followingRedirects()
        ->get('/admin')
        ->assertOk()
        ->assertSee('data-test="panel-switcher"', false)
        ->assertSee('href="'.filament()->getUrl().'"', false)
        ->assertSee('href="'.route('crosswords.index').'"', false);
});
