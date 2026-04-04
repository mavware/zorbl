<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth to save progress', function () {
    $crossword = Crossword::factory()->published()->create();

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
    ])->assertUnauthorized();
});

it('creates a new attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);
});

it('updates existing attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
    ])->assertSuccessful();
});

it('lists user attempts', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->count(3)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/attempts')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

it('shows attempt for specific crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    PuzzleAttempt::factory()->for($user)->for($crossword)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/crosswords/{$crossword->id}/attempt")
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);
});
