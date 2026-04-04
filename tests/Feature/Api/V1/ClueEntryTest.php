<?php

use App\Models\ClueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists clue entries', function () {
    $user = User::factory()->create();

    ClueEntry::create([
        'answer' => 'CAT',
        'clue' => 'A feline pet',
        'user_id' => $user->id,
    ]);

    ClueEntry::create([
        'answer' => 'DOG',
        'clue' => 'A canine pet',
        'user_id' => $user->id,
    ]);

    $this->getJson('/api/v1/clues')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('filters by answer', function () {
    $user = User::factory()->create();

    ClueEntry::create([
        'answer' => 'CAT',
        'clue' => 'A feline pet',
        'user_id' => $user->id,
    ]);

    ClueEntry::create([
        'answer' => 'DOG',
        'clue' => 'A canine pet',
        'user_id' => $user->id,
    ]);

    $response = $this->getJson('/api/v1/clues?filter[answer]=CAT');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
