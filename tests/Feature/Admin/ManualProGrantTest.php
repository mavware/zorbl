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

test('a user with a past start and null end is Pro', function () {
    $user = User::factory()->create([
        'manual_pro_started_at' => now()->subDay(),
        'manual_pro_ended_at' => null,
    ]);

    expect($user->isPro())->toBeTrue();
});

test('a user with a past start and future end is Pro', function () {
    $user = User::factory()->create([
        'manual_pro_started_at' => now()->subDay(),
        'manual_pro_ended_at' => now()->addMonth(),
    ]);

    expect($user->isPro())->toBeTrue();
});

test('a user with a future start is not yet Pro', function () {
    $user = User::factory()->create([
        'manual_pro_started_at' => now()->addDay(),
        'manual_pro_ended_at' => null,
    ]);

    expect($user->isPro())->toBeFalse();
});

test('a user whose end has passed is no longer Pro', function () {
    $user = User::factory()->create([
        'manual_pro_started_at' => now()->subMonths(2),
        'manual_pro_ended_at' => now()->subDay(),
    ]);

    expect($user->isPro())->toBeFalse();
});

test('admin can grant Pro indefinitely from the users table', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('grantPro')->table($user), [
            'manual_pro_ended_at' => null,
        ])
        ->assertNotified();

    $fresh = $user->fresh();
    expect($fresh->manual_pro_started_at)->not->toBeNull()
        ->and($fresh->manual_pro_ended_at)->toBeNull()
        ->and($fresh->isPro())->toBeTrue();
});

test('admin can grant Pro with an expiration date', function () {
    $user = User::factory()->create();
    $end = now()->addMonth()->startOfMinute();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('grantPro')->table($user), [
            'manual_pro_ended_at' => $end->toDateTimeString(),
        ])
        ->assertNotified();

    $fresh = $user->fresh();
    expect($fresh->manual_pro_started_at)->not->toBeNull()
        ->and($fresh->manual_pro_ended_at->equalTo($end))->toBeTrue()
        ->and($fresh->isPro())->toBeTrue();
});

test('admin can end an active Pro grant from the users table', function () {
    $user = User::factory()->create([
        'manual_pro_started_at' => now()->subDay(),
        'manual_pro_ended_at' => null,
    ]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('grantPro')->table($user))
        ->assertNotified();

    $fresh = $user->fresh();
    expect($fresh->manual_pro_ended_at)->not->toBeNull()
        ->and($fresh->manual_pro_ended_at->isFuture())->toBeFalse()
        ->and($fresh->isPro())->toBeFalse();
});

test('admin can set the manual Pro window via the edit form', function () {
    $user = User::factory()->create();
    $start = now()->startOfMinute();
    $end = now()->addMonth()->startOfMinute();

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm([
            'manual_pro_started_at' => $start->toDateTimeString(),
            'manual_pro_ended_at' => $end->toDateTimeString(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $user->fresh();
    expect($fresh->manual_pro_started_at->equalTo($start))->toBeTrue()
        ->and($fresh->manual_pro_ended_at->equalTo($end))->toBeTrue();
});
