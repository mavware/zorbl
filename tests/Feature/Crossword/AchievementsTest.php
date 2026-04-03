<?php

use App\Models\Achievement;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Services\AchievementService;

test('first solve awards first_solve achievement', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $service = app(AchievementService::class);
    $earned = $service->processSolve($user);

    expect($earned)->toHaveCount(1)
        ->and($earned[0]->type)->toBe('first_solve');
});

test('duplicate achievement is not awarded twice', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $service = app(AchievementService::class);
    $service->processSolve($user);
    $earned = $service->processSolve($user);

    expect($earned)->toHaveCount(0)
        ->and(Achievement::where('user_id', $user->id)->count())->toBe(1);
});

test('streak updates correctly on consecutive days', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $service = app(AchievementService::class);

    // Day 1
    $service->updateStreak($user);
    expect($user->fresh()->current_streak)->toBe(1);

    // Simulate next day
    $user->last_solve_date = now()->subDay()->toDateString();
    $user->save();

    $service->updateStreak($user->fresh());
    expect($user->fresh()->current_streak)->toBe(2);
});

test('streak resets after missing a day', function () {
    $user = User::factory()->create([
        'current_streak' => 5,
        'longest_streak' => 5,
        'last_solve_date' => now()->subDays(2)->toDateString(),
    ]);

    $service = app(AchievementService::class);
    $service->updateStreak($user);

    expect($user->fresh()->current_streak)->toBe(1)
        ->and($user->fresh()->longest_streak)->toBe(5);
});

test('longest streak is preserved', function () {
    $user = User::factory()->create([
        'current_streak' => 10,
        'longest_streak' => 10,
        'last_solve_date' => now()->subDays(3)->toDateString(),
    ]);

    $service = app(AchievementService::class);
    $service->updateStreak($user);

    // Streak reset to 1, but longest preserved
    expect($user->fresh()->current_streak)->toBe(1)
        ->and($user->fresh()->longest_streak)->toBe(10);
});

test('speed demon achievement is awarded for fast solve', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create([
        'solve_time_seconds' => 90,
    ]);

    $service = app(AchievementService::class);
    $earned = $service->processSolve($user, 90);

    $types = collect($earned)->pluck('type')->all();
    expect($types)->toContain('speed_demon');
});

test('speed demon is not awarded for slow solve', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create([
        'solve_time_seconds' => 300,
    ]);

    $service = app(AchievementService::class);
    $earned = $service->processSolve($user, 300);

    $types = collect($earned)->pluck('type')->all();
    expect($types)->not->toContain('speed_demon');
});

test('stats page shows streak and achievements', function () {
    $user = User::factory()->create([
        'current_streak' => 3,
        'longest_streak' => 7,
    ]);

    Achievement::create([
        'user_id' => $user->id,
        'type' => 'first_solve',
        'label' => 'First Solve',
        'description' => 'Completed your first crossword puzzle',
        'icon' => 'star',
        'earned_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.stats'))
        ->assertOk()
        ->assertSee('3 days')
        ->assertSee('Best: 7 days')
        ->assertSee('First Solve');
});
