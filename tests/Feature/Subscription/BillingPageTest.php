<?php

use App\Models\AiUsage;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

function makeBillingProUser(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    return $user;
}

it('loads the billing page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk();
});

it('shows free plan for non-subscribers', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('Free Plan')
        ->assertSee('Upgrade to Pro');
});

it('shows pro plan for subscribers', function () {
    $user = makeBillingProUser();

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('Pro Plan')
        ->assertSee('Manage Subscription')
        ->assertDontSee('Upgrade to Pro');
});

it('shows AI usage for pro users', function () {
    $user = makeBillingProUser();

    AiUsage::create(['user_id' => $user->id, 'type' => 'grid_fill']);
    AiUsage::create(['user_id' => $user->id, 'type' => 'grid_fill']);
    AiUsage::create(['user_id' => $user->id, 'type' => 'clue_generation']);

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('AI Usage This Month')
        ->assertSee('2 / 50')
        ->assertSee('1 / 50');
});

it('does not show AI usage for free users', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertDontSee('AI Usage This Month');
});

it('requires authentication', function () {
    $this->get(route('billing.index'))
        ->assertRedirect(route('login'));
});
