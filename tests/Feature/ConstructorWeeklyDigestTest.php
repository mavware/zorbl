<?php

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use App\Notifications\ConstructorWeeklyDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── Command integration ──────────────────────────────────────────────────

test('digest is sent to constructors with activity in the past week', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();
    $solver = User::factory()->create();

    PuzzleAttempt::factory()->completed()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(3),
        'completed_at' => now()->subDays(3),
    ]);

    $this->artisan('constructors:send-weekly-digest')
        ->expectsOutputToContain('Sent 1 digest(s)')
        ->assertSuccessful();

    Notification::assertSentTo($constructor, ConstructorWeeklyDigest::class);
});

test('digest is not sent to constructors with no activity', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();

    $this->artisan('constructors:send-weekly-digest')
        ->expectsOutputToContain('skipped 1')
        ->assertSuccessful();

    Notification::assertNotSentTo($constructor, ConstructorWeeklyDigest::class);
});

test('digest is not sent to users with no published puzzles', function () {
    Notification::fake();

    $user = User::factory()->create();
    Crossword::factory()->for($user)->create(['is_published' => false]);

    $this->artisan('constructors:send-weekly-digest')
        ->expectsOutputToContain('Sent 0')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

test('digest skips activity older than the reporting window', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();
    $solver = User::factory()->create();

    PuzzleAttempt::factory()->completed()->for($solver)->for($crossword)->create([
        'created_at' => now()->subDays(10),
        'completed_at' => now()->subDays(10),
    ]);

    $this->artisan('constructors:send-weekly-digest')
        ->assertSuccessful();

    Notification::assertNotSentTo($constructor, ConstructorWeeklyDigest::class);
});

test('digest includes likes in stats', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();
    $liker = User::factory()->create();

    CrosswordLike::factory()->for($liker)->for($crossword)->create([
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('constructors:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($constructor, ConstructorWeeklyDigest::class, function ($notification) {
        return $notification->stats['new_likes'] === 1;
    });
});

test('digest includes comments in stats', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();
    $commenter = User::factory()->create();

    PuzzleComment::factory()->for($commenter)->for($crossword)->create([
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('constructors:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($constructor, ConstructorWeeklyDigest::class, function ($notification) {
        return $notification->stats['new_comments'] === 1;
    });
});

test('digest includes new followers in stats', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();
    $follower = User::factory()->create();

    Follow::factory()->create([
        'follower_id' => $follower->id,
        'following_id' => $constructor->id,
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('constructors:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($constructor, ConstructorWeeklyDigest::class, function ($notification) {
        return $notification->stats['new_followers'] === 1;
    });
});

test('digest identifies the top puzzle by solve count', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $popular = Crossword::factory()->published()->for($constructor)->create(['title' => 'Popular Puzzle']);
    $quiet = Crossword::factory()->published()->for($constructor)->create(['title' => 'Quiet Puzzle']);

    foreach (range(1, 3) as $_) {
        PuzzleAttempt::factory()->for(User::factory())->for($popular)->create([
            'created_at' => now()->subDays(2),
        ]);
    }
    PuzzleAttempt::factory()->for(User::factory())->for($quiet)->create([
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan('constructors:send-weekly-digest')->assertSuccessful();

    Notification::assertSentTo($constructor, ConstructorWeeklyDigest::class, function ($notification) {
        return $notification->stats['top_puzzle']['title'] === 'Popular Puzzle'
            && $notification->stats['top_puzzle']['solves'] === 3;
    });
});

test('custom --since flag narrows the reporting window', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();

    PuzzleAttempt::factory()->for(User::factory())->for($crossword)->create([
        'created_at' => now()->subDays(5),
    ]);

    PuzzleAttempt::factory()->completed()->for(User::factory())->for($crossword)->create([
        'created_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
    ]);

    $this->artisan('constructors:send-weekly-digest', [
        '--since' => now()->subDays(3)->toDateString(),
    ])->assertSuccessful();

    Notification::assertSentTo($constructor, ConstructorWeeklyDigest::class, function ($notification) {
        return $notification->stats['new_solves'] === 1;
    });
});

// ─── Notification preferences ─────────────────────────────────────────────

test('digest respects opt-out via notification preferences', function () {
    $constructor = User::factory()->create([
        'notification_preferences' => [
            NotificationType::WeeklyDigest->value => false,
        ],
    ]);

    $notification = new ConstructorWeeklyDigest([
        'new_solves' => 5,
        'new_completions' => 3,
        'new_likes' => 1,
        'new_comments' => 0,
        'new_followers' => 0,
        'top_puzzle' => null,
    ]);

    expect($notification->via($constructor))->toBe([]);
});

test('digest is sent when preference is enabled (default)', function () {
    $constructor = User::factory()->create();

    $notification = new ConstructorWeeklyDigest([
        'new_solves' => 5,
        'new_completions' => 3,
        'new_likes' => 1,
        'new_comments' => 0,
        'new_followers' => 0,
        'top_puzzle' => null,
    ]);

    expect($notification->via($constructor))->toBe(['mail']);
});

// ─── Notification payload ─────────────────────────────────────────────────

test('digest email contains activity summary lines', function () {
    $constructor = User::factory()->create(['name' => 'Alice']);

    $notification = new ConstructorWeeklyDigest([
        'new_solves' => 10,
        'new_completions' => 7,
        'new_likes' => 3,
        'new_comments' => 2,
        'new_followers' => 1,
        'top_puzzle' => ['title' => 'Fun Grid', 'solves' => 10],
    ]);

    $mail = $notification->toMail($constructor);

    $rendered = $mail->render()->toHtml();

    expect($rendered)->toContain('10 new solve attempt(s)')
        ->and($rendered)->toContain('7 puzzle completion(s)')
        ->and($rendered)->toContain('3 new like(s)')
        ->and($rendered)->toContain('2 new comment(s)')
        ->and($rendered)->toContain('1 new follower(s)')
        ->and($rendered)->toContain('Fun Grid');
});

test('digest email shows quiet-week message when all stats are zero', function () {
    $constructor = User::factory()->create(['name' => 'Bob']);

    $notification = new ConstructorWeeklyDigest([
        'new_solves' => 0,
        'new_completions' => 0,
        'new_likes' => 0,
        'new_comments' => 0,
        'new_followers' => 0,
        'top_puzzle' => null,
    ]);

    $mail = $notification->toMail($constructor);
    $rendered = $mail->render()->toHtml();

    expect($rendered)->toContain('quiet week');
});

test('digest is sent via mail channel only', function () {
    $constructor = User::factory()->create();

    $notification = new ConstructorWeeklyDigest([
        'new_solves' => 5,
        'new_completions' => 3,
        'new_likes' => 1,
        'new_comments' => 0,
        'new_followers' => 0,
        'top_puzzle' => null,
    ]);

    $channels = $notification->via($constructor);

    expect($channels)->toBe(['mail']);
});

// ─── Notification preferences page ────────────────────────────────────────

test('weekly digest type appears on notification preferences page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('notifications.edit'))
        ->assertOk()
        ->assertSee(NotificationType::WeeklyDigest->label());
});
