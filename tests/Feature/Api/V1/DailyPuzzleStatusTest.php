<?php

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $response = $this->getJson('/api/v1/daily-puzzle/status');

    $response->assertUnauthorized();
});

it('returns solved status when user has completed today\'s puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 325,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.date', today()->toDateString())
        ->assertJsonPath('data.has_daily_puzzle', true)
        ->assertJsonPath('data.solved', true)
        ->assertJsonPath('data.solve_time_seconds', 325)
        ->assertJsonPath('data.solve_time_formatted', '5:25')
        ->assertJsonPath('data.crossword_id', $crossword->id);
});

it('returns unsolved status when user has not attempted today\'s puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.date', today()->toDateString())
        ->assertJsonPath('data.has_daily_puzzle', true)
        ->assertJsonPath('data.solved', false)
        ->assertJsonPath('data.solve_time_seconds', null)
        ->assertJsonPath('data.solve_time_formatted', null)
        ->assertJsonPath('data.crossword_id', $crossword->id);
});

it('returns unsolved status when user has an incomplete attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);
    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => false,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.solved', false)
        ->assertJsonPath('data.solve_time_seconds', null);
});

it('returns no daily puzzle when none exists', function () {
    $user = User::factory()->create();

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.date', today()->toDateString())
        ->assertJsonPath('data.has_daily_puzzle', false)
        ->assertJsonPath('data.solved', false)
        ->assertJsonPath('data.crossword_id', null);
});

it('works with auto-selected daily puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Auto Daily',
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 180,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.has_daily_puzzle', true)
        ->assertJsonPath('data.solved', true)
        ->assertJsonPath('data.solve_time_seconds', 180)
        ->assertJsonPath('data.crossword_id', $crossword->id);
});

it('does not return another user\'s solve status', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $otherUser->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 200,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.solved', false)
        ->assertJsonPath('data.solve_time_seconds', null);
});

it('returns correct response structure', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'date',
                'has_daily_puzzle',
                'solved',
                'solve_time_seconds',
                'solve_time_formatted',
                'crossword_id',
            ],
        ]);
});

it('formats solve time with hours correctly', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();
    DailyPuzzle::factory()->create([
        'date' => today()->toDateString(),
        'crossword_id' => $crossword->id,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 3725,
    ]);

    Cache::flush();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/daily-puzzle/status');

    $response->assertSuccessful()
        ->assertJsonPath('data.solve_time_formatted', '1:02:05');
});
