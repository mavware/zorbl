<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.id', (string) $user->id)
        ->assertJsonPath('data.attributes.email', $user->email);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('updates the user profile', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', [
        'name' => 'Updated Name',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.attributes.name', 'Updated Name');

    expect($user->fresh()->name)->toBe('Updated Name');
});

it('returns user stats', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/stats')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'puzzles_solved',
                ],
            ],
        ]);
});
