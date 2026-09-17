<?php

use App\Filament\Resources\Contests\ContestResource;
use App\Models\Contest;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * The contest feature is switched off via config/crosswordbuilder.php. These
 * tests cover the "off" state; the rest of the contest suites run with the
 * flag enabled through phpunit.xml.
 */
beforeEach(function () {
    config(['crosswordbuilder.features.contests' => false]);
});

function makeActiveContest(): Contest
{
    return Contest::factory()->create([
        'title' => 'Spring Showdown',
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);
}

test('contest web pages return 404 when contests are disabled', function () {
    $contest = makeActiveContest();

    $this->actingAs(User::factory()->create());

    $this->get('/contests')->assertNotFound();
    $this->get('/contests/'.$contest->slug)->assertNotFound();
    $this->get('/contests/'.$contest->slug.'/leaderboard')->assertNotFound();
});

test('contest api endpoints return 404 when contests are disabled', function () {
    $contest = makeActiveContest();

    $this->getJson('/api/v1/contests')->assertNotFound();
    $this->getJson('/api/v1/contests/'.$contest->slug)->assertNotFound();
});

test('contest web pages work again when the flag is re-enabled', function () {
    config(['crosswordbuilder.features.contests' => true]);
    makeActiveContest();

    $this->actingAs(User::factory()->create());

    $this->get('/contests')->assertOk()->assertSee('Spring Showdown');
});

test('admin contest resource is hidden and unreachable when contests are disabled', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    expect(ContestResource::shouldRegisterNavigation())->toBeFalse()
        ->and(ContestResource::canAccess())->toBeFalse();

    $this->actingAs($admin)
        ->get('/admin/contests')
        ->assertForbidden();
});

test('sidebar and solving page hide contests when disabled', function () {
    makeActiveContest();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertDontSee('Spring Showdown')
        ->assertDontSee('/contests');
});

test('solving page lists active contests when enabled', function () {
    config(['crosswordbuilder.features.contests' => true]);
    makeActiveContest();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Spring Showdown');
});

test('welcome page hides the contest FAQ when disabled', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Can I run a contest with my puzzles?');
});
