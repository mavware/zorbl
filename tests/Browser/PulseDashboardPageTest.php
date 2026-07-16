<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

it('renders the pulse widgets with their styles on the admin pulse page', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    visit('/admin/pulse')
        ->on()->desktop()
        ->assertSee('Application Usage')
        ->assertSee('Slow Queries')
        ->assertSee('Exceptions')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true);
});
