<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\User;
use Livewire\Livewire;

// --- HTTP Access ---

test('contest index page shows active contests', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->create(['title' => 'Active Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertSee('Active Contest');
});

test('contest index page shows upcoming contests', function () {
    $user = User::factory()->create();
    Contest::factory()->upcoming()->create(['title' => 'Upcoming Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertSee('Upcoming Contest');
});

test('contest index page shows past contests', function () {
    $user = User::factory()->create();
    Contest::factory()->ended()->create(['title' => 'Past Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertSee('Past Contest');
});

test('contest index page hides draft contests', function () {
    $user = User::factory()->create();
    Contest::factory()->draft()->create(['title' => 'Draft Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertDontSee('Draft Contest');
});

test('guests cannot access contest index', function () {
    $this->get(route('contests.index'))
        ->assertRedirect();
});

// --- Active Contests Computed Property ---

test('active contests computed property returns only active contests', function () {
    $user = User::factory()->create();

    Contest::factory()->active()->create(['title' => 'Live Now']);
    Contest::factory()->upcoming()->create(['title' => 'Coming Soon']);
    Contest::factory()->ended()->create(['title' => 'Already Over']);
    Contest::factory()->draft()->create(['title' => 'Still Drafting']);

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $active = $component->get('activeContests');

    expect($active)->toHaveCount(1)
        ->and($active->first()->title)->toBe('Live Now');
});

test('active contests include entry and crossword counts', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    $crosswords = Crossword::factory()->count(3)->published()->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    ContestEntry::factory()->count(5)->for($contest)->create();

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $active = $component->get('activeContests');

    expect($active->first()->crosswords_count)->toBe(3)
        ->and($active->first()->entries_count)->toBe(5);
});

test('active contests are ordered by start date descending', function () {
    $user = User::factory()->create();

    Contest::factory()->active()->create([
        'title' => 'Started Earlier',
        'starts_at' => now()->subDays(3),
    ]);
    Contest::factory()->active()->create([
        'title' => 'Started Recently',
        'starts_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $active = $component->get('activeContests');

    expect($active->first()->title)->toBe('Started Recently');
});

// --- Upcoming Contests Computed Property ---

test('upcoming contests computed property returns only upcoming contests', function () {
    $user = User::factory()->create();

    Contest::factory()->active()->create(['title' => 'Active One']);
    Contest::factory()->upcoming()->create(['title' => 'Future One']);
    Contest::factory()->ended()->create(['title' => 'Past One']);

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $upcoming = $component->get('upcomingContests');

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming->first()->title)->toBe('Future One');
});

test('upcoming contests are ordered by start date ascending', function () {
    $user = User::factory()->create();

    Contest::factory()->upcoming()->create([
        'title' => 'Starts Later',
        'starts_at' => now()->addDays(10),
    ]);
    Contest::factory()->upcoming()->create([
        'title' => 'Starts Sooner',
        'starts_at' => now()->addDays(2),
    ]);

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $upcoming = $component->get('upcomingContests');

    expect($upcoming->first()->title)->toBe('Starts Sooner');
});

test('upcoming contests include entry and crossword counts', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->upcoming()->create();

    $crosswords = Crossword::factory()->count(2)->published()->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    ContestEntry::factory()->count(3)->for($contest)->create();

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $upcoming = $component->get('upcomingContests');

    expect($upcoming->first()->crosswords_count)->toBe(2)
        ->and($upcoming->first()->entries_count)->toBe(3);
});

// --- Past Contests Computed Property ---

test('past contests computed property returns only ended contests', function () {
    $user = User::factory()->create();

    Contest::factory()->active()->create(['title' => 'Still Active']);
    Contest::factory()->ended()->create(['title' => 'Finished']);

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $past = $component->get('pastContests');

    expect($past)->toHaveCount(1)
        ->and($past->first()->title)->toBe('Finished');
});

test('past contests are ordered by end date descending', function () {
    $user = User::factory()->create();

    Contest::factory()->ended()->create([
        'title' => 'Ended Long Ago',
        'ends_at' => now()->subDays(30),
    ]);
    Contest::factory()->ended()->create([
        'title' => 'Ended Recently',
        'ends_at' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $past = $component->get('pastContests');

    expect($past->first()->title)->toBe('Ended Recently');
});

test('past contests are limited to 12', function () {
    $user = User::factory()->create();

    Contest::factory()->count(15)->ended()->create();

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $past = $component->get('pastContests');

    expect($past)->toHaveCount(12);
});

test('past contests include entry and crossword counts', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create();

    $crosswords = Crossword::factory()->count(4)->published()->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    ContestEntry::factory()->count(8)->for($contest)->create();

    $component = Livewire::actingAs($user)->test('pages::contests.index');
    $past = $component->get('pastContests');

    expect($past->first()->crosswords_count)->toBe(4)
        ->and($past->first()->entries_count)->toBe(8);
});

// --- Empty States ---

test('empty state is shown when no active contests exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertSee('No active contests')
        ->assertSee('Check back soon');
});

test('upcoming section is hidden when no upcoming contests', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertDontSee('Upcoming Contests');
});

test('past section is hidden when no past contests', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertDontSee('Past Contests');
});

// --- Badge Display ---

test('active contests display Active badge', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertSee('Active');
});

test('featured contests display Featured badge', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->featured()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertSee('Featured');
});

test('ended contests display Ended badge', function () {
    $user = User::factory()->create();
    Contest::factory()->ended()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertSee('Ended');
});

test('upcoming contests display Upcoming badge', function () {
    $user = User::factory()->create();
    Contest::factory()->upcoming()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertSee('Upcoming');
});

// --- Date Display ---

test('contest date range is displayed', function () {
    $user = User::factory()->create();
    $startsAt = now()->subDays(2);
    $endsAt = now()->addDays(5);

    Contest::factory()->create([
        'status' => 'active',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertSee($startsAt->format('M j'))
        ->assertSee($endsAt->format('M j'));
});
