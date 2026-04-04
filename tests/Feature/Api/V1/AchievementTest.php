<?php

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth', function () {
    $this->getJson('/api/v1/me/achievements')
        ->assertUnauthorized();
});

it('lists user achievements', function () {
    $user = User::factory()->create();

    Achievement::create([
        'user_id' => $user->id,
        'type' => 'first_solve',
        'label' => 'First Solve',
        'description' => 'Solved your first puzzle',
        'earned_at' => now(),
    ]);

    Achievement::create([
        'user_id' => $user->id,
        'type' => 'speed_demon',
        'label' => 'Speed Demon',
        'description' => 'Solved a puzzle in under 5 minutes',
        'earned_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/achievements')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
