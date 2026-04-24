<?php

use App\Models\Crossword;
use App\Models\FavoriteList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth', function () {
    $this->getJson('/api/v1/favorites')
        ->assertUnauthorized();
});

it('lists favorite lists', function () {
    $user = User::factory()->create();

    FavoriteList::factory()->count(2)->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/favorites')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('creates a favorite list', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/favorites', ['name' => 'My Favorites'])
        ->assertCreated();
});

it('deletes a favorite list', function () {
    $user = User::factory()->create();
    $list = FavoriteList::factory()->create(['user_id' => $user->id]);

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/favorites/{$list->id}")
        ->assertNoContent();
});

it('adds crossword to list', function () {
    $user = User::factory()->create();
    $list = FavoriteList::factory()->create(['user_id' => $user->id]);
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/favorites/{$list->id}/crosswords", [
        'crossword' => $crossword->id,
    ])->assertSuccessful();
});

it('removes crossword from list', function () {
    $user = User::factory()->create();
    $list = FavoriteList::factory()->create(['user_id' => $user->id]);
    $crossword = Crossword::factory()->published()->create();
    $list->crosswords()->attach($crossword);

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/favorites/{$list->id}/crosswords/{$crossword->id}")
        ->assertNoContent();
});

it('prevents deleting other user list', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = FavoriteList::factory()->create(['user_id' => $otherUser->id]);

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/favorites/{$list->id}")
        ->assertForbidden();
});

it('prevents adding crossword to other user list', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = FavoriteList::factory()->create(['user_id' => $otherUser->id]);
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/favorites/{$list->id}/crosswords", [
        'crossword' => $crossword->id,
    ])->assertForbidden();
});

it('prevents removing crossword from other user list', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $list = FavoriteList::factory()->create(['user_id' => $otherUser->id]);
    $crossword = Crossword::factory()->published()->create();
    $list->crosswords()->attach($crossword);

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/favorites/{$list->id}/crosswords/{$crossword->id}")
        ->assertForbidden();
});
