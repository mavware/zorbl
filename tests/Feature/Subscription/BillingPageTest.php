<?php

use App\Models\AiUsage;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

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

it('shows the subscribe section and no plan card for non-subscribers', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('Support our work')
        ->assertSee('Support Crossword Builder')
        ->assertDontSee('Free Plan')
        ->assertDontSee('Pro Plan')
        ->assertDontSee('Manage Billing')
        ->assertDontSee('Yearly');
});

it('thanks subscribers and lets them manage their subscription', function () {
    $user = makeBillingProUser();

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('Thank you for supporting Crossword Builder')
        ->assertSee('Manage Billing')
        ->assertSeeHtml('data-supporter-badge')
        ->assertDontSee('Pro Plan')
        ->assertDontSee('Support our work');
});

it('tells subscribers on a grace period when their support ends', function () {
    $user = makeBillingProUser();
    $user->subscription('default')->update(['ends_at' => now()->addDays(10)]);

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('Your subscription ends on '.now()->addDays(10)->format('M j, Y'))
        ->assertSee('Manage Billing');
});

it('does not thank admins who have Pro access without subscribing', function () {
    $admin = User::factory()->create();
    Role::findOrCreate('Admin');
    $admin->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::settings.billing')
        ->assertSee('Manage Subscription')
        ->assertDontSee('Thank you for supporting Crossword Builder')
        ->assertDontSeeHtml('data-supporter-badge');
});

it('shows the funding goal progress from the non-subscription baseline', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertSee('Our monthly goal')
        ->assertSee('$215 of $500')
        ->assertSee('43% of the way there')
        ->assertSeeHtml('style="width: 43%"');
});

it('adds five dollars to the goal progress per active subscriber', function () {
    makeBillingProUser();
    makeBillingProUser();

    // A cancelled subscription whose grace period has ended no longer counts.
    $lapsed = makeBillingProUser();
    $lapsed->subscription('default')->update(['ends_at' => now()->subDay()]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.billing')
        ->assertSee('$225 of $500')
        ->assertSee('2 supporter(s)');
});

it('does not show AI usage on the billing page', function () {
    $user = makeBillingProUser();
    AiUsage::create(['user_id' => $user->id, 'type' => 'grid_fill']);

    Livewire::actingAs($user)
        ->test('pages::settings.billing')
        ->assertDontSee('AI Usage This Month');
});

it('requires authentication', function () {
    $this->get(route('billing.index'))
        ->assertRedirect(route('login'));
});
