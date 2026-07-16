<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Pulse\Livewire\Exceptions;
use Laravel\Pulse\Livewire\Queues;
use Laravel\Pulse\Livewire\Servers;
use Laravel\Pulse\Livewire\SlowJobs;
use Laravel\Pulse\Livewire\SlowOutgoingRequests;
use Laravel\Pulse\Livewire\SlowQueries;
use Laravel\Pulse\Livewire\SlowRequests;
use Laravel\Pulse\Livewire\Usage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin', 'web');
});

test('admins can view the pulse dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/pulse')
        ->assertOk();
});

test('non-admin users cannot view the pulse dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/pulse')
        ->assertForbidden();
});

test('guests cannot view the pulse dashboard', function () {
    $this->get('/pulse')->assertForbidden();
});

test('pulse card query results survive a cache round-trip', function (string $card) {
    // Serialize cached values like the production database store does, so the
    // cache.serializable_classes allowlist is exercised.
    config(['cache.stores.array.serialize' => true]);
    Cache::forgetDriver('array');

    Livewire::test($card, ['lazy' => false]);

    // A second render within the 5 second query cache hits the cache and
    // unserializes the stored collections.
    Livewire::test($card, ['lazy' => false])->assertOk();
})->with([
    Servers::class,
    Usage::class,
    Queues::class,
    Laravel\Pulse\Livewire\Cache::class,
    SlowQueries::class,
    Exceptions::class,
    SlowRequests::class,
    SlowJobs::class,
    SlowOutgoingRequests::class,
]);
