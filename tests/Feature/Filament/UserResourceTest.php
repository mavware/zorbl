<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can view user list', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($users);
});

test('admin can search users by name', function () {
    $target = User::factory()->create(['name' => 'Searchable User']);
    User::factory()->create(['name' => 'Other Person']);

    Livewire::test(ListUsers::class)
        ->searchTable('Searchable User')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords(User::where('name', 'Other Person')->get());
});

test('admin can search users by email', function () {
    $target = User::factory()->create(['email' => 'findme@example.com']);
    User::factory()->create(['email' => 'hidden@example.com']);

    Livewire::test(ListUsers::class)
        ->searchTable('findme@example.com')
        ->assertCanSeeTableRecords([$target]);
});

test('admin can create a user', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
    ]);
});

test('creating a user requires name and email', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => null,
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
        ])
        ->assertNotNotified();
});

test('admin can edit a user', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'name' => 'Updated Name',
            'password' => 'newpassword123',
        ])
        ->call('save')
        ->assertNotified();

    expect($user->fresh()->name)->toBe('Updated Name');
});

test('admin can assign roles to a user', function () {
    $editorRole = Role::findOrCreate('Editor', 'web');
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'roles' => [$editorRole->id],
            'password' => 'password123',
        ])
        ->call('save')
        ->assertNotified();

    expect($user->fresh()->hasRole('Editor'))->toBeTrue();
});

test('admin can delete a user', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction('delete')
        ->assertNotified();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('non-admin cannot access user list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});
