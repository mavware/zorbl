<?php

use App\Models\User;
use App\Services\AnonymousUserManager;
use Laravel\Cashier\Subscription;
use Spatie\Permission\Models\Role;

test('free users see the upgrade banner above the support menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Support our work', false)
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
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('Support our work', false);
});

test('admins do not see the upgrade banner', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('Support our work', false);
});

test('guests do not see the upgrade banner', function () {
    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('Help keep Crossword Builder free', false);
});

test('guests do not see the favorites link', function () {
    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('Favorites', false);
});

test('registered users see the favorites link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Favorites', false);
});

test('guests do not see the user menu', function () {
    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('data-test="sidebar-menu-button"', false)
        ->assertDontSee('Log out', false);
});

test('registered users see the user menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-test="sidebar-menu-button"', false)
        ->assertSee('Log out', false);
});

test('admins see an Admin link in the sidebar below Support', function () {
    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $html = $this->actingAs($admin)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee(route('filament.admin.home'), false)
        ->getContent();

    expect(strpos($html, route('filament.admin.home')))
        ->toBeGreaterThan(strpos($html, route('support.index')));
});

test('non-admins do not see the Admin link', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee(route('filament.admin.home'), false);
});
