<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth to like', function () {
    $crossword = Crossword::factory()->published()->create();

    $this->postJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertUnauthorized();
});

it('likes a crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertCreated();

    $this->assertDatabaseHas('crossword_likes', [
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
    ]);
});

it('unlikes a crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    CrosswordLike::factory()->for($user)->for($crossword)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertNoContent();

    $this->assertDatabaseMissing('crossword_likes', [
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
    ]);
});
