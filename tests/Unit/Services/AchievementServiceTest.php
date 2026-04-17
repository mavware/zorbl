<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// --- updateStreak ---

test('updateStreak starts a new streak on first solve', function () {
    $user = User::factory()->create([
        'current_streak' => 0,
        'longest_streak' => 0,
        'last_solve_date' => null,
    ]);

    app(AchievementService::class)->updateStreak($user);

    expect($user->current_streak)->toBe(1)
        ->and($user->longest_streak)->toBe(1)
        ->and(Carbon::parse($user->last_solve_date)->toDateString())->toBe(Carbon::today()->toDateString());
});

test('updateStreak extends streak on consecutive day', function () {
    $user = User::factory()->create([
        'current_streak' => 3,
        'longest_streak' => 5,
        'last_solve_date' => Carbon::yesterday()->toDateString(),
    ]);

    app(AchievementService::class)->updateStreak($user);

    expect($user->current_streak)->toBe(4)
        ->and($user->longest_streak)->toBe(5);
});

test('updateStreak updates longest streak when current exceeds it', function () {
    $user = User::factory()->create([
        'current_streak' => 5,
        'longest_streak' => 5,
        'last_solve_date' => Carbon::yesterday()->toDateString(),
    ]);

    app(AchievementService::class)->updateStreak($user);

    expect($user->current_streak)->toBe(6)
        ->and($user->longest_streak)->toBe(6);
});

test('updateStreak resets streak after a gap day', function () {
    $user = User::factory()->create([
        'current_streak' => 10,
        'longest_streak' => 10,
        'last_solve_date' => Carbon::today()->subDays(2)->toDateString(),
    ]);

    app(AchievementService::class)->updateStreak($user);

    expect($user->current_streak)->toBe(1)
        ->and($user->longest_streak)->toBe(10);
});

test('updateStreak does nothing if already solved today', function () {
    $user = User::factory()->create([
        'current_streak' => 3,
        'longest_streak' => 5,
        'last_solve_date' => Carbon::today()->toDateString(),
    ]);

    app(AchievementService::class)->updateStreak($user);

    expect($user->current_streak)->toBe(3)
        ->and($user->longest_streak)->toBe(5);
});

// --- checkAchievements: milestones ---

test('checkAchievements awards first_solve after 1 completed puzzle', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    expect($earned)->toHaveCount(1)
        ->and($earned[0]->type)->toBe('first_solve');
});

test('checkAchievements awards puzzles_10 at 10 completions', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->count(10)->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_solve')
        ->and($types)->toContain('puzzles_10');
});

test('checkAchievements awards puzzles_50 at 50 completions', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->count(50)->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('puzzles_50');
});

test('checkAchievements awards puzzles_100 at 100 completions', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->count(100)->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('puzzles_100');
});

// --- checkAchievements: streaks ---

test('checkAchievements awards streak_7 when current streak is 7', function () {
    $user = User::factory()->create([
        'current_streak' => 7,
    ]);
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('streak_7');
});

test('checkAchievements awards streak_30 when current streak is 30', function () {
    $user = User::factory()->create([
        'current_streak' => 30,
    ]);
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('streak_7')
        ->and($types)->toContain('streak_30');
});

test('checkAchievements does not award streak if below threshold', function () {
    $user = User::factory()->create([
        'current_streak' => 6,
    ]);
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('streak_7');
});

// --- checkAchievements: speed ---

test('checkAchievements awards speed_demon for solve under 2 minutes', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user, solveTimeSeconds: 90);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('speed_demon');
});

test('checkAchievements awards speed_demon at exactly 120 seconds', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user, solveTimeSeconds: 120);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('speed_demon');
});

test('checkAchievements does not award speed_demon for solve over 2 minutes', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user, solveTimeSeconds: 121);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('speed_demon');
});

test('checkAchievements does not award speed_demon for zero seconds', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user, solveTimeSeconds: 0);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('speed_demon');
});

test('checkAchievements does not award speed_demon for null seconds', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->checkAchievements($user, solveTimeSeconds: null);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('speed_demon');
});

// --- idempotency ---

test('checkAchievements does not duplicate already earned achievements', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $service = app(AchievementService::class);
    $first = $service->checkAchievements($user);
    $second = $service->checkAchievements($user);

    expect($first)->toHaveCount(1)
        ->and($first[0]->type)->toBe('first_solve')
        ->and($second)->toHaveCount(0);
});

// --- processSolve ---

test('processSolve updates streak and returns new achievements', function () {
    $user = User::factory()->create([
        'current_streak' => 0,
        'longest_streak' => 0,
        'last_solve_date' => null,
    ]);
    PuzzleAttempt::factory()->for($user)->completed()->create();

    $earned = app(AchievementService::class)->processSolve($user, solveTimeSeconds: 60);

    expect($user->current_streak)->toBe(1)
        ->and($user->longest_streak)->toBe(1);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_solve')
        ->and($types)->toContain('speed_demon');
});

// --- checkContestAchievements ---

test('checkContestAchievements awards first_contest when all puzzles completed', function () {
    $contest = Contest::factory()->active()->create();
    $crosswords = Crossword::factory()->published()->count(3)->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->create(['puzzles_completed' => 3]);

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_contest');
});

test('checkContestAchievements does not award first_contest when puzzles incomplete', function () {
    $contest = Contest::factory()->active()->create();
    $crosswords = Crossword::factory()->published()->count(3)->create();
    $contest->crosswords()->attach($crosswords->pluck('id'));

    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->create(['puzzles_completed' => 2]);

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('first_contest');
});

test('checkContestAchievements awards first_meta_solve when meta is solved', function () {
    $contest = Contest::factory()->active()->create();
    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->metaSolved()
        ->create();

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_meta_solve');
});

test('checkContestAchievements does not award first_meta_solve when meta not solved', function () {
    $contest = Contest::factory()->active()->create();
    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->create(['meta_solved' => false]);

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('first_meta_solve');
});

test('checkContestAchievements awards contest_winner for rank 1 in ended contest', function () {
    $contest = Contest::factory()->ended()->create();
    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->create(['rank' => 1]);

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('contest_winner');
});

test('checkContestAchievements does not award contest_winner for rank 2', function () {
    $contest = Contest::factory()->ended()->create();
    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->create(['rank' => 2]);

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('contest_winner');
});

test('checkContestAchievements does not award contest_winner for rank 1 in active contest', function () {
    $contest = Contest::factory()->active()->create();
    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->create(['rank' => 1]);

    $earned = app(AchievementService::class)->checkContestAchievements($user, $entry);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('contest_winner');
});

// --- award idempotency for contest achievements ---

test('checkContestAchievements does not duplicate already earned contest achievements', function () {
    $contest = Contest::factory()->ended()->create();
    $user = User::factory()->create();
    $entry = ContestEntry::factory()
        ->for($contest)
        ->for($user)
        ->metaSolved()
        ->create(['rank' => 1]);

    $service = app(AchievementService::class);
    $first = $service->checkContestAchievements($user, $entry);
    $second = $service->checkContestAchievements($user, $entry);

    expect(count($first))->toBeGreaterThan(0)
        ->and($second)->toHaveCount(0);
});

// --- award edge cases ---

test('award returns null for invalid achievement type', function () {
    $user = User::factory()->create();

    $service = app(AchievementService::class);
    $method = new ReflectionMethod($service, 'award');

    $result = $method->invoke($service, $user, 'nonexistent_type', []);

    expect($result)->toBeNull();
});

test('achievement records are persisted to the database', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->for($user)->completed()->create();

    app(AchievementService::class)->checkAchievements($user);

    $this->assertDatabaseHas('achievements', [
        'user_id' => $user->id,
        'type' => 'first_solve',
        'label' => 'First Solve',
        'icon' => 'star',
    ]);
});
