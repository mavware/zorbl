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
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    ClueEntry::create([
        'answer' => 'DOG',
        'clue' => 'A canine pet',
        'user_id' => $user->id,
        'status' => ClueEntry::STATUS_APPROVED,
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
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    ClueEntry::create([
        'answer' => 'DOG',
        'clue' => 'A canine pet',
        'user_id' => $user->id,
        'status' => ClueEntry::STATUS_APPROVED,
    ]);

    $response = $this->getJson('/api/v1/clues?filter[answer]=CAT');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('excludes pending clues from the public listing', function () {
    $user = User::factory()->create();

    ClueEntry::create([
        'answer' => 'SECRET',
        'clue' => 'Should not appear',
        'user_id' => $user->id,
        'status' => ClueEntry::STATUS_PENDING,
    ]);

    $this->getJson('/api/v1/clues')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});
