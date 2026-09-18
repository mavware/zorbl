<?php

use App\Models\User;
use App\Support\SupporterGoal;
use Laravel\Cashier\Subscription;

function makeGoalSubscription(array $overrides = []): Subscription
{
    $user = User::factory()->create(['stripe_id' => 'cus_goal_'.uniqid()]);

    return Subscription::create(array_merge([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_goal_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ], $overrides));
}

it('starts from the non-subscription baseline with no supporters', function () {
    $goal = new SupporterGoal;

    expect($goal->supporterCount())->toBe(0)
        ->and($goal->currentDollars())->toBe(215)
        ->and($goal->goalDollars())->toBe(500)
        ->and($goal->percent())->toBe(43)
        ->and($goal->isReached())->toBeFalse();
});

it('counts five dollars per active or grace-period supporter', function () {
    makeGoalSubscription();
    makeGoalSubscription(['ends_at' => now()->addWeek()]);

    // Ended, unpaid, and non-default subscriptions are ignored.
    makeGoalSubscription(['ends_at' => now()->subDay()]);
    makeGoalSubscription(['stripe_status' => 'unpaid']);
    makeGoalSubscription(['type' => 'addon']);

    $goal = new SupporterGoal;

    expect($goal->supporterCount())->toBe(2)
        ->and($goal->currentDollars())->toBe(225)
        ->and($goal->percent())->toBe(45);
});

it('caps the percentage at 100 once the goal is reached', function () {
    foreach (range(1, 60) as $i) {
        makeGoalSubscription();
    }

    $goal = new SupporterGoal;

    expect($goal->currentDollars())->toBe(515)
        ->and($goal->percent())->toBe(100)
        ->and($goal->isReached())->toBeTrue();
});
