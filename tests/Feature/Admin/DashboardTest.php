<?php

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
