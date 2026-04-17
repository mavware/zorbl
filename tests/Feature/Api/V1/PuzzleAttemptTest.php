<?php

use App\Models\Achievement;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires auth to save progress', function () {
    $crossword = Crossword::factory()->published()->create();

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
    ])->assertUnauthorized();
});

it('creates a new attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);
});

it('updates existing attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
    ])->assertSuccessful();
});

it('lists user attempts', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->count(3)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/attempts')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['type', 'id', 'attributes'],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

it('shows attempt for specific crossword', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    PuzzleAttempt::factory()->for($user)->for($crossword)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/crosswords/{$crossword->id}/attempt")
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes',
            ],
        ]);
});

it('triggers achievements when completing a puzzle via API', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertCreated();

    expect(Achievement::where('user_id', $user->id)->where('type', 'first_solve')->exists())->toBeTrue();
});

it('updates streak when completing a puzzle via API', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertCreated();

    $user->refresh();
    expect($user->current_streak)->toBe(1)
        ->and($user->last_solve_date)->not->toBeNull();
});

it('awards speed demon achievement for fast API solve', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 90,
    ])->assertCreated();

    expect(Achievement::where('user_id', $user->id)->where('type', 'speed_demon')->exists())->toBeTrue();
});

it('does not trigger achievements for non-completion saves', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => false,
    ])->assertCreated();

    expect(Achievement::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->current_streak)->toBe(0);
});

it('does not re-trigger achievements for already completed puzzles', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertSuccessful();

    expect(Achievement::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->current_streak)->toBe(0);
});

it('syncs contest progress when completing a contest puzzle via API', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    $contest = Contest::factory()->active()->create();
    $contest->crosswords()->attach($crossword, ['sort_order' => 1]);

    $entry = ContestEntry::factory()->for($user)->for($contest)->create([
        'registered_at' => now(),
        'puzzles_completed' => 0,
    ]);

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/crosswords/{$crossword->id}/attempt", [
        'progress' => Crossword::emptySolution(15, 15),
        'is_completed' => true,
        'solve_time_seconds' => 300,
    ])->assertCreated();

    $entry->refresh();
    expect($entry->puzzles_completed)->toBe(1)
        ->and($entry->total_solve_time_seconds)->toBe(300);
});
