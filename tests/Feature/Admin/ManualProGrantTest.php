<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('a user without a manual grant or subscription is not Pro', function () {
    $user = User::factory()->create();

    expect($user->isPro())->toBeFalse();
});

test('setting manual_pro_granted_at makes the user Pro', function () {
    $user = User::factory()->create(['manual_pro_granted_at' => now()]);

    expect($user->isPro())->toBeTrue();
});

test('admin can grant Pro to a user from the users table', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('grantPro')->table($user))
        ->assertNotified();

    expect($user->fresh()->manual_pro_granted_at)->not->toBeNull()
        ->and($user->fresh()->isPro())->toBeTrue();
});

test('admin can revoke a manual Pro grant from the users table', function () {
    $user = User::factory()->create(['manual_pro_granted_at' => now()]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('grantPro')->table($user))
        ->assertNotified();

    expect($user->fresh()->manual_pro_granted_at)->toBeNull()
        ->and($user->fresh()->isPro())->toBeFalse();
});

test('admin can set manual_pro_granted_at via the edit form', function () {
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['manual_pro_granted_at' => now()->toDateTimeString()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->manual_pro_granted_at)->not->toBeNull();
});
