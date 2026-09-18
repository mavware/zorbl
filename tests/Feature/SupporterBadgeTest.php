<?php

use App\Models\Crossword;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Spatie\Permission\Models\Role;

function makeSupporter(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_badge_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_badge_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    return $user;
}

it('reports supporters only for users with an active subscription', function () {
    $supporter = makeSupporter();
    $free = User::factory()->create();

    $admin = User::factory()->create();
    Role::findOrCreate('Admin');
    $admin->assignRole('Admin');

    expect($supporter->isSupporter())->toBeTrue()
        ->and($free->isSupporter())->toBeFalse()
        // Admins are Pro but haven't subscribed, so they get no supporter badge.
        ->and($admin->isPro())->toBeTrue()
        ->and($admin->isSupporter())->toBeFalse();
});

it('shows the badge next to a supporter on their public constructor profile', function () {
    $supporter = makeSupporter();
    Crossword::factory()->published()->for($supporter)->create();

    $free = User::factory()->create();
    Crossword::factory()->published()->for($free)->create();

    $this->get(route('constructors.show', $supporter))
        ->assertOk()
        ->assertSee('data-supporter-badge', false)
        ->assertSee('Supporter');

    $this->get(route('constructors.show', $free))
        ->assertOk()
        ->assertDontSee('data-supporter-badge', false);
});

it('shows the badge in the constructors directory only for supporters', function () {
    $supporter = makeSupporter();
    Crossword::factory()->published()->for($supporter)->create();

    $free = User::factory()->create();
    Crossword::factory()->published()->for($free)->create();

    $html = $this->get(route('constructors.index'))->assertOk()->getContent();

    expect(substr_count($html, 'data-supporter-badge'))->toBe(1);
});

it('shows the badge next to the signed-in supporter in the sidebar menu', function () {
    $supporter = makeSupporter();

    $this->actingAs($supporter)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-supporter-badge', false);

    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('data-supporter-badge', false);
});

it('shows the badge next to a supporting author on the public solve page', function () {
    $supporter = makeSupporter();
    $crossword = Crossword::factory()->published()->for($supporter)->create();

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('data-supporter-badge', false);
});
