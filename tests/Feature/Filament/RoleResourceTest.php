<?php

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
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

test('admin can view role list', function () {
    Role::findOrCreate('Editor', 'web');
    Role::findOrCreate('Moderator', 'web');

    Livewire::test(ListRoles::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(Role::all());
});

test('admin can create a role', function () {
    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'Moderator',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertNotified();

    expect(Role::where('name', 'Moderator')->exists())->toBeTrue();
});

test('creating a role requires a name', function () {
    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => null,
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertNotNotified();
});

test('role names must be unique', function () {
    Role::findOrCreate('Editor', 'web');

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'Editor',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique'])
        ->assertNotNotified();
});

test('admin can edit a role', function () {
    $role = Role::findOrCreate('Editor', 'web');

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm([
            'name' => 'Senior Editor',
        ])
        ->call('save')
        ->assertNotified();

    expect($role->fresh()->name)->toBe('Senior Editor');
});

test('admin can assign permissions to a role', function () {
    $role = Role::findOrCreate('Editor', 'web');
    $permission = Permission::findOrCreate('manage-crosswords', 'web');

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm([
            'permissions' => [$permission->id],
        ])
        ->call('save')
        ->assertNotified();

    expect($role->fresh()->hasPermissionTo('manage-crosswords'))->toBeTrue();
});

test('admin can delete a role', function () {
    $role = Role::findOrCreate('Temporary', 'web');

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->callAction('delete')
        ->assertNotified();

    expect(Role::where('name', 'Temporary')->exists())->toBeFalse();
});

test('non-admin cannot access roles admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/roles')
        ->assertForbidden();
});
