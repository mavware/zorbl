<?php

use App\Enums\NotificationType;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Notifications\PuzzleMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── reachedMilestone unit tests ──────────────────────────────────────────

it('returns the milestone when count exactly matches a threshold', function (int $count, int $expected) {
    expect(PuzzleMilestone::reachedMilestone($count))->toBe($expected);
})->with([
    [10, 10],
    [25, 25],
    [50, 50],
    [100, 100],
    [250, 250],
    [500, 500],
    [1000, 1000],
]);

it('returns null when count does not match any threshold', function (int $count) {
    expect(PuzzleMilestone::reachedMilestone($count))->toBeNull();
})->with([0, 1, 9, 11, 24, 26, 49, 51, 99, 101, 249, 999]);

// ─── API integration ──────────────────────────────────────────────────────

it('sends milestone notification when puzzle reaches 10 solves via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 9,
    ]);

    PuzzleAttempt::factory()
        ->for($crossword)
        ->completed()
        ->count(9)
        ->create();

    $solver = User::factory()->create();
    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'is_completed' => true,
        'solve_time_seconds' => 120,
    ])->assertCreated();

    Notification::assertSentTo($constructor, PuzzleMilestone::class, function ($notification) use ($crossword) {
        return $notification->crossword->id === $crossword->id
            && $notification->milestone === 10;
    });
});

it('does not send milestone notification when count is not a threshold via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 7,
    ]);

    PuzzleAttempt::factory()
        ->for($crossword)
        ->completed()
        ->count(7)
        ->create();

    $solver = User::factory()->create();
    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'is_completed' => true,
        'solve_time_seconds' => 120,
    ])->assertCreated();

    Notification::assertNotSentTo($constructor, PuzzleMilestone::class);
});

it('does not send milestone notification when solving own puzzle via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 9,
    ]);

    PuzzleAttempt::factory()
        ->for($crossword)
        ->completed()
        ->count(9)
        ->create();

    Sanctum::actingAs($constructor);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'is_completed' => true,
        'solve_time_seconds' => 120,
    ])->assertCreated();

    Notification::assertNotSentTo($constructor, PuzzleMilestone::class);
});

it('does not send milestone notification on non-completion save via API', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 9,
    ]);

    $solver = User::factory()->create();
    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'is_completed' => false,
    ])->assertCreated();

    Notification::assertNotSentTo($constructor, PuzzleMilestone::class);
});

it('respects notification preference opt-out for milestones', function () {
    Notification::fake();

    $constructor = User::factory()->create([
        'notification_preferences' => [
            NotificationType::PuzzleMilestone->value => false,
        ],
    ]);
    $crossword = Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 9,
    ]);

    PuzzleAttempt::factory()
        ->for($crossword)
        ->completed()
        ->count(9)
        ->create();

    $solver = User::factory()->create();
    Sanctum::actingAs($solver);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution($crossword->width, $crossword->height),
        'is_completed' => true,
        'solve_time_seconds' => 120,
    ])->assertCreated();

    Notification::assertNotSentTo($constructor, PuzzleMilestone::class);
});

// ─── Livewire solver integration ──────────────────────────────────────────

test('constructor is notified of milestone when solver completes puzzle via Livewire', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 3,
        'height' => 3,
        'cached_completed_count' => 9,
    ]);

    PuzzleAttempt::factory()
        ->for($crossword)
        ->completed()
        ->count(9)
        ->create();

    $solver = User::factory()->create();
    $progress = Crossword::emptySolution(3, 3);

    Livewire::actingAs($solver)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, true, 120);

    Notification::assertSentTo($constructor, PuzzleMilestone::class, function ($notification) use ($crossword) {
        return $notification->crossword->id === $crossword->id
            && $notification->milestone === 10;
    });
});

// ─── Notification payload ─────────────────────────────────────────────────

it('formats the milestone notification payload correctly', function () {
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create(['title' => 'Sunday Stumper']);

    $notification = new PuzzleMilestone($crossword, 100);
    $data = $notification->toArray($constructor);

    expect($data['type'])->toBe('puzzle.milestone')
        ->and($data['title'])->toContain('Sunday Stumper')
        ->and($data['title'])->toContain('100')
        ->and($data['crossword_id'])->toBe($crossword->id)
        ->and($data['milestone'])->toBe(100);
});

// ─── NotificationType enum ────────────────────────────────────────────────

it('includes puzzle milestone in NotificationType enum', function () {
    $type = NotificationType::PuzzleMilestone;

    expect($type->value)->toBe('puzzle_milestone')
        ->and($type->label())->toBeString()
        ->and($type->description())->toBeString();
});
