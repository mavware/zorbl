<?php

use App\Models\Contest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists published contests', function () {
    Contest::factory()->active()->count(2)->create();
    Contest::factory()->upcoming()->create();
    Contest::factory()->draft()->create();
    Contest::factory()->create(['status' => 'archived']);

    $response = $this->getJson('/api/v1/contests');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('published scope excludes draft and archived', function () {
    Contest::factory()->active()->create();
    Contest::factory()->upcoming()->create();
    Contest::factory()->ended()->create();
    Contest::factory()->draft()->create();
    Contest::factory()->create(['status' => 'archived']);

    expect(Contest::published()->count())->toBe(3);
});

it('shows a contest', function () {
    $contest = Contest::factory()->active()->create();

    $this->getJson("/api/v1/contests/{$contest->slug}")
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'contests')
        ->assertJsonPath('data.attributes.title', $contest->title)
        ->assertJsonPath('data.attributes.slug', $contest->slug)
        ->assertJsonPath('data.attributes.status', 'active');
});

it('never exposes meta_answer', function () {
    $contest = Contest::factory()->active()->create();

    $response = $this->getJson("/api/v1/contests/{$contest->slug}");

    $response->assertSuccessful();
    expect($response->json('data.attributes'))->not->toHaveKey('meta_answer');
});

it('shows leaderboard', function () {
    $contest = Contest::factory()->active()->create();

    $this->getJson("/api/v1/contests/{$contest->slug}/leaderboard")
        ->assertSuccessful();
});
