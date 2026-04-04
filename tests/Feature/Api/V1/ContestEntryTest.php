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
