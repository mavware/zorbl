<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\DeleteAction;
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

test('non-admin cannot access user list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

test('admin can search users by name', function () {
    $targetUser = User::factory()->create(['name' => 'Unique Findable Name']);
    User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->searchTable('Unique Findable Name')
        ->assertCanSeeTableRecords([$targetUser])
        ->assertCountTableRecords(1);
});

test('admin can search users by email', function () {
    $targetUser = User::factory()->create(['email' => 'searchable@example.test']);
    User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->searchTable('searchable@example.test')
        ->assertCanSeeTableRecords([$targetUser])
        ->assertCountTableRecords(1);
});

test('admin can create a user', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Test User',
            'email' => 'newuser@example.test',
            'password' => 'securepassword',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    $this->assertDatabaseHas(User::class, [
        'name' => 'New Test User',
        'email' => 'newuser@example.test',
    ]);
});

test('create user requires name', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => 'valid@example.test',
            'password' => 'securepassword',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertNotNotified();
});

test('create user requires email', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => null,
            'password' => 'securepassword',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required'])
        ->assertNotNotified();
});

test('create user requires valid email', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'securepassword',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email'])
        ->assertNotNotified();
});

test('create user requires password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'valid@example.test',
            'password' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required'])
        ->assertNotNotified();
});

test('admin can edit a user', function () {
    $user = User::factory()->create(['name' => 'Original Name']);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertFormSet([
            'name' => 'Original Name',
        ])
        ->fillForm([
            'name' => 'Updated Name',
        ])
        ->call('save')
        ->assertNotified();

    expect($user->fresh()->name)->toBe('Updated Name');
});

test('admin can delete a user', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $this->assertModelMissing($user);
});

test('user list displays role badges', function () {
    $editorRole = Role::findOrCreate('Editor', 'web');
    $user = User::factory()->create();
    $user->assignRole('Editor');

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$user]);
});
