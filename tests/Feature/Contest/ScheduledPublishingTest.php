<?php

use App\Models\Contest;
use App\Models\User;
use Livewire\Livewire;

// --- Model behavior ---

test('isPublished returns false for upcoming contest with future publish_at', function () {
    $contest = new Contest([
        'status' => 'upcoming',
        'publish_at' => now()->addDay(),
    ]);

    expect($contest->isPublished())->toBeFalse();
});

test('isPublished returns true for upcoming contest with past publish_at', function () {
    $contest = new Contest([
        'status' => 'upcoming',
        'publish_at' => now()->subDay(),
    ]);

    expect($contest->isPublished())->toBeTrue();
});

test('isPublished returns true for upcoming contest with null publish_at', function () {
    $contest = new Contest([
        'status' => 'upcoming',
        'publish_at' => null,
    ]);

    expect($contest->isPublished())->toBeTrue();
});

test('isPublished returns false for draft regardless of publish_at', function () {
    $contest = new Contest([
        'status' => 'draft',
        'publish_at' => now()->subDay(),
    ]);

    expect($contest->isPublished())->toBeFalse();
});

// --- Published scope ---

test('published scope excludes contests with future publish_at', function () {
    Contest::factory()->create([
        'status' => 'upcoming',
        'publish_at' => now()->addDay(),
    ]);
    Contest::factory()->create([
        'status' => 'active',
        'publish_at' => now()->subDay(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(6),
    ]);
    Contest::factory()->create([
        'status' => 'upcoming',
        'publish_at' => null,
    ]);

    $published = Contest::published()->get();

    expect($published)->toHaveCount(2);
});

// --- Artisan command ---

test('publish-scheduled command transitions draft contests past their publish_at to upcoming', function () {
    $shouldPublish = Contest::factory()->create([
        'status' => 'draft',
        'publish_at' => now()->subHour(),
    ]);
    $notYet = Contest::factory()->create([
        'status' => 'draft',
        'publish_at' => now()->addDay(),
    ]);
    $noSchedule = Contest::factory()->create([
        'status' => 'draft',
        'publish_at' => null,
    ]);

    $this->artisan('contests:publish-scheduled')
        ->assertSuccessful()
        ->expectsOutputToContain("Published contest: {$shouldPublish->title}");

    expect($shouldPublish->fresh()->status)->toBe('upcoming')
        ->and($notYet->fresh()->status)->toBe('draft')
        ->and($noSchedule->fresh()->status)->toBe('draft');
});

test('publish-scheduled command reports no contests when none are ready', function () {
    Contest::factory()->create([
        'status' => 'draft',
        'publish_at' => now()->addDay(),
    ]);

    $this->artisan('contests:publish-scheduled')
        ->assertSuccessful()
        ->expectsOutputToContain('No contests to publish.');
});

test('publish-scheduled command ignores non-draft contests', function () {
    $activeContest = Contest::factory()->active()->create([
        'publish_at' => now()->subDay(),
    ]);

    $this->artisan('contests:publish-scheduled')
        ->assertSuccessful();

    expect($activeContest->fresh()->status)->toBe('active');
});

// --- Listing page visibility ---

test('contest with future publish_at is not shown in contest listing', function () {
    $user = User::factory()->create();
    Contest::factory()->create([
        'status' => 'upcoming',
        'title' => 'Hidden Contest',
        'publish_at' => now()->addDay(),
    ]);
    Contest::factory()->active()->create([
        'title' => 'Visible Contest',
        'publish_at' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.index')
        ->assertDontSee('Hidden Contest')
        ->assertSee('Visible Contest');
});

// --- Factory state ---

test('scheduledPublish factory state creates a draft with publish_at', function () {
    $contest = Contest::factory()->scheduledPublish()->create();

    expect($contest->status)->toBe('draft')
        ->and($contest->publish_at)->not->toBeNull();
});
