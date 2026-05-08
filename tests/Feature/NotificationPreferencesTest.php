<?php

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use App\Notifications\CrosswordLiked;
use App\Notifications\NewFollower;
use App\Notifications\NewPuzzleComment;
use App\Notifications\NewPuzzlePublished;
use App\Notifications\PuzzleCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Settings page ────────────────────────────────────────────────────────

test('notification preferences page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('notifications.edit'))->assertOk();
});

test('notification preferences page requires authentication', function () {
    $this->get(route('notifications.edit'))->assertRedirect(route('login'));
});

test('all notification types are shown on the preferences page', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('notifications.edit'));

    foreach (NotificationType::cases() as $type) {
        $response->assertSee($type->label());
    }
});

test('toggling a preference disables the notification type', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->call('toggle', NotificationType::CrosswordLiked->value)
        ->assertDispatched('notification-preferences-updated');

    $user->refresh();

    expect($user->notification_preferences[NotificationType::CrosswordLiked->value])->toBeFalse();
});

test('toggling a disabled preference re-enables it', function () {
    $user = User::factory()->create([
        'notification_preferences' => [
            NotificationType::CrosswordLiked->value => false,
        ],
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->call('toggle', NotificationType::CrosswordLiked->value)
        ->assertDispatched('notification-preferences-updated');

    $user->refresh();

    expect($user->notification_preferences[NotificationType::CrosswordLiked->value])->toBeTrue();
});

test('toggling an invalid notification type does nothing', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.notifications')
        ->call('toggle', 'invalid_type')
        ->assertNotDispatched('notification-preferences-updated');

    $user->refresh();

    expect($user->notification_preferences)->toBeNull();
});

// ─── User model ───────────────────────────────────────────────────────────

test('wantsNotification defaults to true when no preferences set', function () {
    $user = User::factory()->make();

    expect($user->wantsNotification(NotificationType::CrosswordLiked->value))->toBeTrue();
    expect($user->wantsNotification(NotificationType::PuzzleCompleted->value))->toBeTrue();
});

test('wantsNotification returns false when preference is disabled', function () {
    $user = User::factory()->make([
        'notification_preferences' => [
            NotificationType::CrosswordLiked->value => false,
        ],
    ]);

    expect($user->wantsNotification(NotificationType::CrosswordLiked->value))->toBeFalse();
    expect($user->wantsNotification(NotificationType::PuzzleCompleted->value))->toBeTrue();
});

// ─── Notification delivery respects preferences ───────────────────────────

test('CrosswordLiked is not sent when user opts out', function () {
    $user = User::factory()->create([
        'notification_preferences' => [
            NotificationType::CrosswordLiked->value => false,
        ],
    ]);

    $crossword = Crossword::factory()->for($user)->create();
    $liker = User::factory()->create();

    $notification = new CrosswordLiked($crossword, $liker);

    expect($notification->via($user))->toBe([]);
});

test('CrosswordLiked is sent when user has not opted out', function () {
    $user = User::factory()->create();

    $crossword = Crossword::factory()->for($user)->create();
    $liker = User::factory()->create();

    $notification = new CrosswordLiked($crossword, $liker);

    expect($notification->via($user))->toBe(['database']);
});

test('PuzzleCompleted is not sent when user opts out', function () {
    $user = User::factory()->create([
        'notification_preferences' => [
            NotificationType::PuzzleCompleted->value => false,
        ],
    ]);

    $crossword = Crossword::factory()->for($user)->create();
    $solver = User::factory()->create();

    $notification = new PuzzleCompleted($crossword, $solver, 120);

    expect($notification->via($user))->toBe([]);
});

test('NewFollower is not sent when user opts out', function () {
    $user = User::factory()->create([
        'notification_preferences' => [
            NotificationType::NewFollower->value => false,
        ],
    ]);

    $follower = User::factory()->create();

    $notification = new NewFollower($follower);

    expect($notification->via($user))->toBe([]);
});

test('NewPuzzlePublished is not sent when user opts out', function () {
    $user = User::factory()->create([
        'notification_preferences' => [
            NotificationType::NewPuzzlePublished->value => false,
        ],
    ]);

    $crossword = Crossword::factory()->create();
    $constructor = User::factory()->create();

    $notification = new NewPuzzlePublished($crossword, $constructor);

    expect($notification->via($user))->toBe([]);
});

test('NewPuzzleComment is not sent when user opts out', function () {
    $user = User::factory()->create([
        'notification_preferences' => [
            NotificationType::NewPuzzleComment->value => false,
        ],
    ]);

    $crossword = Crossword::factory()->for($user)->create();
    $commenter = User::factory()->create();
    $comment = PuzzleComment::factory()->for($crossword)->create(['user_id' => $commenter->id]);

    $notification = new NewPuzzleComment($comment, $commenter);

    expect($notification->via($user))->toBe([]);
});
