<?php

use App\Models\Crossword;
use App\Models\User;
use App\Support\AiUsageTracker;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

function makeProUser(): User
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

function makeCrosswordForUser(User $user): Crossword
{
    return Crossword::factory()->for($user)->create([
        'title' => 'Test Puzzle',
        'width' => 3,
        'height' => 3,
        'grid' => [
            [1, 2, '#'],
            [3, 0, 4],
            ['#', 5, 0],
        ],
        'solution' => [
            ['C', 'A', '#'],
            ['B', 'O', 'T'],
            ['#', 'L', 'O'],
        ],
        'clues_across' => [
            ['number' => 1, 'clue' => 'CA'],
            ['number' => 3, 'clue' => 'BOT'],
            ['number' => 5, 'clue' => 'LO'],
        ],
        'clues_down' => [
            ['number' => 1, 'clue' => 'CB'],
            ['number' => 2, 'clue' => 'AOL'],
            ['number' => 4, 'clue' => 'TO'],
        ],
    ]);
}

test('free user has zero AI fill allowance', function () {
    $user = User::factory()->create();

    $tracker = app(AiUsageTracker::class);

    expect($user->isPro())->toBeFalse()
        ->and($tracker->canUse($user, 'grid_fill'))->toBeFalse()
        ->and($user->planLimits()->monthlyAiFills())->toBe(0);
});

test('free user has zero AI clue generation allowance', function () {
    $user = User::factory()->create();

    $tracker = app(AiUsageTracker::class);

    expect($user->isPro())->toBeFalse()
        ->and($tracker->canUse($user, 'clue_generation'))->toBeFalse()
        ->and($user->planLimits()->monthlyAiClues())->toBe(0);
});

test('pro user has AI fill allowance', function () {
    $user = makeProUser();

    $tracker = app(AiUsageTracker::class);

    expect($user->isPro())->toBeTrue()
        ->and($tracker->canUse($user, 'grid_fill'))->toBeTrue()
        ->and($user->planLimits()->monthlyAiFills())->toBe(50);
});

test('free user attempting pro export shows upgrade modal', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'puz')
        ->assertSet('showUpgradeModal', true)
        ->assertSet('upgradeFeature', 'export');
});

test('free user attempting jpz export shows upgrade modal', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'jpz')
        ->assertSet('showUpgradeModal', true)
        ->assertSet('upgradeFeature', 'export');
});

test('free user attempting pdf export shows upgrade modal', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'pdf')
        ->assertSet('showUpgradeModal', true)
        ->assertSet('upgradeFeature', 'export');
});

test('free user can still export ipuz without upgrade prompt', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'ipuz')
        ->assertSet('showUpgradeModal', false);
});

test('pro user does not get upgrade modal for exports', function () {
    $user = makeProUser();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('attemptExport', 'puz')
        ->assertSet('showUpgradeModal', false);
});

test('upgrade modal renders billing link', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('showUpgradeModal', true)
        ->set('upgradeFeature', 'ai_fill')
        ->assertSee('Upgrade to Pro')
        ->assertSee('AI Fill uses Claude')
        ->assertSee('Upgrade Now');
});

test('upgrade modal shows export description for export feature', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('showUpgradeModal', true)
        ->set('upgradeFeature', 'export')
        ->assertSee('Export your puzzles');
});

test('upgrade modal shows ai clues description', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('showUpgradeModal', true)
        ->set('upgradeFeature', 'ai_clues')
        ->assertSee('AI Generate Clues writes creative');
});

test('editor shows Pro badge on AI menu items for free users', function () {
    $user = User::factory()->create();
    $crossword = makeCrosswordForUser($user);

    $html = Livewire::actingAs($user)
        ->test('pages::crosswords.editor', ['crossword' => $crossword])
        ->html();

    expect($html)->toContain('AI Fill')
        ->and($html)->toContain('AI Generate Clues');
});
