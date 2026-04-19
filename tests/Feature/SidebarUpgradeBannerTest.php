<?php

use App\Models\User;
use Laravel\Cashier\Subscription;
use Spatie\Permission\Models\Role;

test('free users see the upgrade banner above the support menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Upgrade to Pro', false)
        ->assertSee(route('billing.index'), false);
});

test('pro subscribers do not see the upgrade banner', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Upgrade to Pro', false);
});

test('admins do not see the upgrade banner', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Upgrade to Pro', false);
});
