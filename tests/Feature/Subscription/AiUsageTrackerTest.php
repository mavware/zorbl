<?php

use App\Models\AiUsage;
use App\Models\User;
use App\Support\AiUsageTracker;
use Laravel\Cashier\Subscription;

/**
 * Helper to create a pro user by inserting a subscription record directly.
 */
function createProUser(): User
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

beforeEach(function () {
    $this->tracker = new AiUsageTracker;
    $this->user = User::factory()->create();
});

it('starts with zero monthly count', function () {
    expect($this->tracker->monthlyCount($this->user, 'grid_fill'))->toBe(0);
});

it('records and counts usage', function () {
    $this->tracker->record($this->user, 'grid_fill');
    $this->tracker->record($this->user, 'grid_fill');
    $this->tracker->record($this->user, 'clue_generation');

    expect($this->tracker->monthlyCount($this->user, 'grid_fill'))->toBe(2)
        ->and($this->tracker->monthlyCount($this->user, 'clue_generation'))->toBe(1);
});

it('resets count at start of new month', function () {
    $this->tracker->record($this->user, 'grid_fill');

    $this->travel(1)->months();

    expect($this->tracker->monthlyCount($this->user, 'grid_fill'))->toBe(0);
});

it('blocks free users from AI features', function () {
    expect($this->tracker->canUse($this->user, 'grid_fill'))->toBeFalse()
        ->and($this->tracker->canUse($this->user, 'clue_generation'))->toBeFalse();
});

it('allows pro users to use AI features', function () {
    $proUser = createProUser();

    expect($this->tracker->canUse($proUser, 'grid_fill'))->toBeTrue();
});

it('blocks pro users when limit reached', function () {
    $proUser = createProUser();

    // Fill all 50 slots
    for ($i = 0; $i < 50; $i++) {
        AiUsage::create(['user_id' => $proUser->id, 'type' => 'grid_fill']);
    }

    expect($this->tracker->canUse($proUser, 'grid_fill'))->toBeFalse();
});

it('calculates remaining uses correctly', function () {
    $proUser = createProUser();

    $this->tracker->record($proUser, 'grid_fill');
    $this->tracker->record($proUser, 'grid_fill');

    expect($this->tracker->remaining($proUser, 'grid_fill'))->toBe(48);
});

it('returns zero remaining for free users', function () {
    expect($this->tracker->remaining($this->user, 'grid_fill'))->toBe(0);
});

it('does not count other users usage', function () {
    $otherUser = User::factory()->create();
    $this->tracker->record($otherUser, 'grid_fill');

    expect($this->tracker->monthlyCount($this->user, 'grid_fill'))->toBe(0);
});
