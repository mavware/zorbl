<?php

use App\Models\Crossword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a constructor profile', function () {
    $user = User::factory()->create();

    $this->getJson("/api/v1/constructors/{$user->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.id', (string) $user->id)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);
});

it('lists constructor crosswords', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->count(2)->create();
    Crossword::factory()->for($user)->create(); // unpublished

    $this->getJson("/api/v1/constructors/{$user->id}/crosswords")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
