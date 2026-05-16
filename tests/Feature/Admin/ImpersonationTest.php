<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

test('the impersonate action is not visible for the admin themselves', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('impersonate', $this->admin);
});

test('the impersonate action is not visible for other admins', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('Admin');

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('impersonate', $otherAdmin);
});

test('an admin can start impersonating a regular user', function () {
    $target = User::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('impersonate')->table($target));

    expect(auth()->id())->toBe($target->id)
        ->and(session(ImpersonationController::SESSION_KEY))->toBe($this->admin->id);
});

test('the stop route returns the original admin to their session', function () {
    $target = User::factory()->create();

    $this->withSession([ImpersonationController::SESSION_KEY => $this->admin->id])
        ->actingAs($target)
        ->post(route('impersonate.stop'))
        ->assertRedirect('/admin/users');

    expect(auth()->id())->toBe($this->admin->id)
        ->and(session()->has(ImpersonationController::SESSION_KEY))->toBeFalse();
});

test('the stop route fails when not currently impersonating', function () {
    $this->actingAs($this->admin);

    $this->post(route('impersonate.stop'))->assertNotFound();
});

test('a non-admin cannot start impersonation via the route', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->post(route('impersonate.start', $target))
        ->assertForbidden();
});

test('an admin cannot impersonate themselves via the route', function () {
    $this->actingAs($this->admin)
        ->post(route('impersonate.start', $this->admin))
        ->assertForbidden();
});

test('an admin cannot impersonate another admin via the route', function () {
    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole('Admin');

    $this->actingAs($this->admin)
        ->post(route('impersonate.start', $otherAdmin))
        ->assertForbidden();
});

test('the route-based start flow works for a regular target', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('impersonate.start', $target))
        ->assertRedirect('/');

    expect(auth()->id())->toBe($target->id)
        ->and(session(ImpersonationController::SESSION_KEY))->toBe($this->admin->id);
});
