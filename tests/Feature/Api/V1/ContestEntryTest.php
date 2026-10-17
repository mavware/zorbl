<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth to register', function () {
    $contest = Contest::factory()->active()->create();

    $this->postJson("/api/v1/contests/{$contest->slug}/register")
        ->assertUnauthorized();
});

it('registers for a contest', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/contests/{$contest->slug}/register")
        ->assertCreated();

    $this->assertDatabaseHas(ContestEntry::class, [
        'contest_id' => $contest->id,
        'user_id' => $user->id,
    ]);
});

it('returns existing entry on duplicate registration', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/contests/{$contest->slug}/register")
        ->assertOk();

    expect(ContestEntry::where('contest_id', $contest->id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);
});

it('shows my entry', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/contests/{$contest->slug}/entry")
        ->assertSuccessful();
});

it('submits meta answer', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create([
        'meta_answer' => 'PUZZLE',
        'max_meta_attempts' => 5,
    ]);

    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/contests/{$contest->slug}/meta", [
        'answer' => 'PUZZLE',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.attributes.correct', true)
        ->assertJsonPath('data.attributes.attempts_remaining', 4);
});

it('stores the meta answer text on submission', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create([
        'meta_answer' => 'CROSSWORD',
        'max_meta_attempts' => 5,
    ]);

    $entry = ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
    ]);

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/contests/{$contest->slug}/meta", [
        'answer' => 'WRONG',
    ]);

    $entry->refresh();
    expect($entry->meta_answer)->toBe('WRONG');
    expect($entry->meta_submitted_at)->not->toBeNull();
    expect($entry->meta_solved)->toBeFalse();
});

it('recalculates leaderboard after meta submission', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $contest = Contest::factory()->active()->create([
        'meta_answer' => 'ANSWER',
        'max_meta_attempts' => 10,
    ]);

    $entry1 = ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user1->id,
    ]);

    $entry2 = ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user2->id,
    ]);

    Sanctum::actingAs($user1);

    $this->postJson("/api/v1/contests/{$contest->slug}/meta", [
        'answer' => 'ANSWER',
    ]);

    $entry1->refresh();
    $entry2->refresh();

    expect($entry1->meta_solved)->toBeTrue();
    expect($entry1->rank)->toBe(1);
    expect($entry2->rank)->toBe(2);
});
