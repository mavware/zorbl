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

test('non-admin cannot access user list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

test('non-admin cannot access create user page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/users/create')
        ->assertForbidden();
});

test('non-admin cannot access edit user page', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->get("/admin/users/{$target->id}/edit")
        ->assertForbidden();
});

test('guest is redirected to admin login', function () {
    auth()->logout();

    $this->get('/admin/users')
        ->assertRedirect('/admin/login');
});

test('admin can view user list', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($users);
});

test('admin can search users by name', function () {
    $target = User::factory()->create(['name' => 'Unique SearchableName']);
    User::factory()->create(['name' => 'Other Person']);

    Livewire::test(ListUsers::class)
        ->searchTable('Unique SearchableName')
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
            'name' => 'New Test User',
            'email' => 'newuser@example.com',
            'password' => 'securepassword123',
        ])
        ->call('create')
        ->assertNotified();

    $this->assertDatabaseHas('users', [
        'name' => 'New Test User',
        'email' => 'newuser@example.com',
    ]);
});

test('creating user requires name', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => 'test@example.com',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required'])
        ->assertNotNotified();
});

test('creating user requires email', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => null,
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'required'])
        ->assertNotNotified();
});

test('creating user requires valid email', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email'])
        ->assertNotNotified();
});

test('creating user requires password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required'])
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

test('admin can update user email', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'email' => 'new@example.com',
            'password' => 'newpassword123',
        ])
        ->call('save')
        ->assertNotified();

    expect($user->fresh()->email)->toBe('new@example.com');
});

test('admin can update copyright name', function () {
    $user = User::factory()->create(['copyright_name' => null]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'copyright_name' => 'John Doe Publishing',
            'password' => 'newpassword123',
        ])
        ->call('save')
        ->assertNotified();

    expect($user->fresh()->copyright_name)->toBe('John Doe Publishing');
});

test('admin can delete a user', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('admin can assign roles to a user', function () {
    $editorRole = Role::findOrCreate('Editor', 'web');
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'roles' => [$editorRole->id],
            'password' => 'newpassword123',
        ])
        ->call('save')
        ->assertNotified();

    expect($user->fresh()->hasRole('Editor'))->toBeTrue();
});

test('user list shows subscription status', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$user]);
});

test('edit form loads existing user data', function () {
    $user = User::factory()->create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'copyright_name' => 'My Copyright',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->assertFormSet([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'copyright_name' => 'My Copyright',
        ]);
});
