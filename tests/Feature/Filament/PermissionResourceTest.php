<?php

use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view permission list', function () {
    Permission::findOrCreate('manage-crosswords', 'web');
    Permission::findOrCreate('manage-users', 'web');

    Livewire::test(ListPermissions::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(Permission::all());
});

test('admin can create a permission', function () {
    Livewire::test(CreatePermission::class)
        ->fillForm([
            'name' => 'manage-contests',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertNotified();

    expect(Permission::where('name', 'manage-contests')->exists())->toBeTrue();
});

test('creating a permission requires a name', function () {
    Livewire::test(CreatePermission::class)
        ->fillForm([
            'name' => null,
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertNotNotified();
});

test('permission names must be unique', function () {
    Permission::findOrCreate('manage-crosswords', 'web');

    Livewire::test(CreatePermission::class)
        ->fillForm([
            'name' => 'manage-crosswords',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique'])
        ->assertNotNotified();
});

test('admin can edit a permission', function () {
    $permission = Permission::findOrCreate('old-permission', 'web');

    Livewire::test(EditPermission::class, ['record' => $permission->id])
        ->fillForm([
            'name' => 'updated-permission',
        ])
        ->call('save')
        ->assertNotified();

    expect($permission->fresh()->name)->toBe('updated-permission');
});

test('admin can delete a permission', function () {
    $permission = Permission::findOrCreate('temporary-permission', 'web');

    Livewire::test(EditPermission::class, ['record' => $permission->id])
        ->callAction('delete')
        ->assertNotified();

    expect(Permission::where('name', 'temporary-permission')->exists())->toBeFalse();
});

test('non-admin cannot access permissions admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/permissions')
        ->assertForbidden();
});
