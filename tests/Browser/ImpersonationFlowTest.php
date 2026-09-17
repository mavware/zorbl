<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Users get distinct password hashes here on purpose. The factory caches one
 * hash for every user in a test, which hides the session password-hash check
 * that runs inside the admin panel.
 */
function makeImpersonationPair(?string $targetPassword = 'target-secret'): array
{
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create(['name' => 'Admin Person', 'password' => Hash::make('admin-secret')]);
    $admin->assignRole('Admin');
    $target = User::factory()->create([
        'name' => 'Target Person',
        'password' => $targetPassword === null ? null : Hash::make($targetPassword),
    ]);

    return [$admin, $target];
}

it('lets an admin impersonate a user and leave impersonation without being logged out', function () {
    [$admin, $target] = makeImpersonationPair();

    $this->actingAs($admin);

    $page = visit('/admin/users')
        ->assertSee('Target Person');

    $page->click('Impersonate')
        ->assertSee('Impersonate Target Person?')
        ->click('Confirm')
        ->assertPathIs('/crosswords')
        ->assertSee('Impersonating')
        ->assertSee('Target Person')
        ->assertNoJavaScriptErrors();

    $page->click('Leave impersonation')
        ->assertPathIs('/admin/users')
        ->assertSee('Target Person')
        ->assertDontSee('Impersonating')
        ->assertDontSee('Sign in');
});

it('lets an admin impersonate a user who signed up with Google and has no password', function () {
    [$admin, $target] = makeImpersonationPair(targetPassword: null);

    $this->actingAs($admin);

    $page = visit('/admin/users')
        ->assertSee('Target Person');

    $page->click('Impersonate')
        ->assertSee('Impersonate Target Person?')
        ->click('Confirm')
        ->assertPathIs('/crosswords')
        ->assertSee('Impersonating')
        ->assertSee('Target Person');

    $page->click('Leave impersonation')
        ->assertPathIs('/admin/users')
        ->assertSee('Target Person')
        ->assertDontSee('Impersonating');
});
