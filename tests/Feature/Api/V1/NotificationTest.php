<?php

use App\Models\Crossword;
use App\Models\User;
use App\Notifications\CrosswordLiked;
use App\Notifications\NewFollower;
use App\Notifications\NewPuzzleComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth to list notifications', function () {
    $this->getJson('/api/v1/notifications')
        ->assertUnauthorized();
});

it('lists notifications for the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create();

    $user->notify(new CrosswordLiked($crossword, $other));
    $user->notify(new NewFollower($other));

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'type',
                    'id',
                    'attributes' => [
                        'notification_type',
                        'title',
                        'body',
                        'url',
                        'read_at',
                        'created_at',
                    ],
                ],
            ],
        ]);
});

it('does not include other user notifications', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $follower = User::factory()->create();

    $otherUser->notify(new NewFollower($follower));

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications')
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('returns the unread notification count', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create();

    $user->notify(new CrosswordLiked($crossword, $other));
    $user->notify(new NewFollower($other));

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertSuccessful()
        ->assertJson(['count' => 2]);
});

it('marks a single notification as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $user->notify(new NewFollower($other));

    $notification = $user->notifications()->first();

    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->published()->create();

    $user->notify(new CrosswordLiked($crossword, $other));
    $user->notify(new NewFollower($other));

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/notifications/mark-all-read')
        ->assertNoContent();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('cannot mark another user notification as read', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $follower = User::factory()->create();

    $otherUser->notify(new NewFollower($follower));

    $notification = $otherUser->notifications()->first();

    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNotFound();
});

it('sends notification when a comment is posted on your puzzle', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $commenter = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->create();

    Sanctum::actingAs($commenter);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/comments", [
        'body' => 'Great puzzle!',
        'rating' => 5,
    ])->assertCreated();

    Notification::assertSentTo($owner, NewPuzzleComment::class);
});

it('does not send notification when commenting on your own puzzle', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->create();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/comments", [
        'body' => 'Self-comment!',
        'rating' => 4,
    ])->assertCreated();

    Notification::assertNotSentTo($owner, NewPuzzleComment::class);
});

it('sends notification when a crossword is liked', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->create();

    Sanctum::actingAs($liker);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertCreated();

    Notification::assertSentTo($owner, CrosswordLiked::class);
});

it('does not send notification when liking your own puzzle', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->create();

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertCreated();

    Notification::assertNotSentTo($owner, CrosswordLiked::class);
});

it('does not send duplicate notification when liking again', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $liker = User::factory()->create();
    $crossword = Crossword::factory()->for($owner)->published()->create();

    Sanctum::actingAs($liker);

    $this->postJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertCreated();
    $this->postJson("/api/v1/crosswords/{$crossword->id}/like")
        ->assertCreated();

    Notification::assertSentToTimes($owner, CrosswordLiked::class, 1);
});
