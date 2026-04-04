<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\User;
use App\Services\AchievementService;

test('first_contest achievement awarded when all puzzles completed', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $crossword = Crossword::factory()->published()->create();
    $contest->crosswords()->attach($crossword->id);

    $entry = ContestEntry::factory()->for($contest)->for($user)->create([
        'puzzles_completed' => 1,
    ]);

    $service = app(AchievementService::class);
    $earned = $service->checkContestAchievements($user, $entry);

    expect($earned)->toHaveCount(1)
        ->and($earned[0]->type)->toBe('first_contest');
});

test('first_meta_solve achievement awarded when meta is solved', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $entry = ContestEntry::factory()->for($contest)->for($user)->create([
        'meta_solved' => true,
        'meta_submitted_at' => now(),
    ]);

    $service = app(AchievementService::class);
    $earned = $service->checkContestAchievements($user, $entry);

    expect(collect($earned)->pluck('type'))->toContain('first_meta_solve');
});

test('contest_winner achievement awarded to rank 1 after contest ends', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create();
    $entry = ContestEntry::factory()->for($contest)->for($user)->create([
        'rank' => 1,
        'meta_solved' => true,
        'meta_submitted_at' => now(),
    ]);

    $service = app(AchievementService::class);
    $earned = $service->checkContestAchievements($user, $entry);

    expect(collect($earned)->pluck('type'))->toContain('contest_winner');
});

test('contest_winner not awarded during active contest', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $entry = ContestEntry::factory()->for($contest)->for($user)->create([
        'rank' => 1,
    ]);

    $service = app(AchievementService::class);
    $earned = $service->checkContestAchievements($user, $entry);

    expect(collect($earned)->pluck('type'))->not->toContain('contest_winner');
});
