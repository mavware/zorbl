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

beforeEach(function () {
    config([
        'crosswordbuilder.external_contributors' => 43,
        'crosswordbuilder.funding_goal_dollars' => 500,
    ]);
});

it('starts from the external contributors with no subscribers', function () {
    $goal = new SupporterGoal;

    expect($goal->supporterCount())->toBe(0)
        ->and($goal->externalContributorCount())->toBe(43)
        ->and($goal->contributorCount())->toBe(43)
        ->and($goal->currentDollars())->toBe(215)
        ->and($goal->goalDollars())->toBe(500)
        ->and($goal->percent())->toBe(43)
        ->and($goal->isReached())->toBeFalse();
});

it('reads the external contributor count from config', function () {
    config(['crosswordbuilder.external_contributors' => 10]);

    $goal = new SupporterGoal;

    expect($goal->externalContributorCount())->toBe(10)
        ->and($goal->contributorCount())->toBe(10)
        ->and($goal->currentDollars())->toBe(50)
        ->and($goal->percent())->toBe(10);
});

it('treats a missing or negative external contributor count as zero', function () {
    config(['crosswordbuilder.external_contributors' => -5]);

    expect((new SupporterGoal)->externalContributorCount())->toBe(0);

    config(['crosswordbuilder.external_contributors' => null]);

    expect((new SupporterGoal)->externalContributorCount())->toBe(0)
        ->and((new SupporterGoal)->currentDollars())->toBe(0);
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
        ->and($goal->contributorCount())->toBe(45)
        ->and($goal->currentDollars())->toBe(225)
        ->and($goal->percent())->toBe(45);
});

it('reads the funding goal from config', function () {
    config(['crosswordbuilder.funding_goal_dollars' => 1000]);

    $goal = new SupporterGoal;

    expect($goal->goalDollars())->toBe(1000)
        ->and($goal->currentDollars())->toBe(215)
        ->and($goal->percent())->toBe(21)
        ->and($goal->isReached())->toBeFalse();
});

it('never lets the funding goal drop below one dollar', function () {
    config(['crosswordbuilder.funding_goal_dollars' => 0]);

    $goal = new SupporterGoal;

    expect($goal->goalDollars())->toBe(1)
        ->and($goal->percent())->toBe(100)
        ->and($goal->isReached())->toBeTrue();
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

it('shows subscribers and external contributors together on the billing page', function () {
    makeGoalSubscription();
    makeGoalSubscription();

    $this->actingAs(User::factory()->create())
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('45 supporter(s)')
        ->assertSee('$225 of $500');
});

it('shows the configured funding goal on the billing page', function () {
    config(['crosswordbuilder.funding_goal_dollars' => 1000]);

    $this->actingAs(User::factory()->create())
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee('$215 of $1,000')
        ->assertSee('21% of the way there');
});
