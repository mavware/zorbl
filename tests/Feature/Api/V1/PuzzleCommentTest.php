<?php

use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists comments for a crossword', function () {
    $crossword = Crossword::factory()->published()->create();
    PuzzleComment::factory()->for($crossword)->count(3)->create();

    $this->getJson("/api/v1/crosswords/{$crossword->id}/comments")
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('requires auth to post comment', function () {
    $crossword = Crossword::factory()->published()->create();

    $this->postJson("/api/v1/crosswords/{$crossword->id}/comments", [
        'body' => 'Great puzzle!',
        'rating' => 5,
    ])->assertUnauthorized();
});

it('posts a comment', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/comments", [
        'body' => 'Great puzzle!',
        'rating' => 5,
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);

    $this->assertDatabaseHas('puzzle_comments', [
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'body' => 'Great puzzle!',
        'rating' => 5,
    ]);
});

it('deletes own comment', function () {
    $user = User::factory()->create();
    $comment = PuzzleComment::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/comments/{$comment->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('puzzle_comments', [
        'id' => $comment->id,
    ]);
});

it('cannot delete other user comment', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $comment = PuzzleComment::factory()->for($otherUser)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/comments/{$comment->id}")
        ->assertForbidden();
});
