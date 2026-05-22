<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- processPublish ---

test('processPublish awards first_publish on first published puzzle', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create();

    $earned = app(AchievementService::class)->processPublish($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_publish');
});

test('processPublish awards published_5 at 5 published puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->count(5)->create();

    $earned = app(AchievementService::class)->processPublish($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_publish')
        ->and($types)->toContain('published_5');
});

test('processPublish awards published_25 at 25 published puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->count(25)->create();

    $earned = app(AchievementService::class)->processPublish($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_publish')
        ->and($types)->toContain('published_5')
        ->and($types)->toContain('published_25');
});

test('processPublish does not award first_publish when no published puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create();

    $earned = app(AchievementService::class)->processPublish($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('first_publish');
});

test('processPublish does not duplicate already earned achievements', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create();

    $service = app(AchievementService::class);
    $first = $service->processPublish($user);
    $second = $service->processPublish($user);

    expect($first)->toHaveCount(1)
        ->and($first[0]->type)->toBe('first_publish')
        ->and($second)->toHaveCount(0);
});

test('processPublish also checks total solves milestones', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create([
        'cached_completed_count' => 100,
    ]);

    $earned = app(AchievementService::class)->processPublish($user);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_publish')
        ->and($types)->toContain('total_solves_100');
});

// --- processLikeReceived ---

test('processLikeReceived awards first_like_received on first like', function () {
    $constructor = User::factory()->create();
    $puzzle = Crossword::factory()->for($constructor)->published()->create();
    CrosswordLike::factory()->create(['crossword_id' => $puzzle->id]);

    $earned = app(AchievementService::class)->processLikeReceived($constructor);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('first_like_received');
});

test('processLikeReceived does not award when no likes exist', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->for($constructor)->published()->create();

    $earned = app(AchievementService::class)->processLikeReceived($constructor);

    expect($earned)->toHaveCount(0);
});

test('processLikeReceived is idempotent', function () {
    $constructor = User::factory()->create();
    $puzzle = Crossword::factory()->for($constructor)->published()->create();
    CrosswordLike::factory()->create(['crossword_id' => $puzzle->id]);

    $service = app(AchievementService::class);
    $first = $service->processLikeReceived($constructor);
    CrosswordLike::factory()->create(['crossword_id' => $puzzle->id]);
    $second = $service->processLikeReceived($constructor);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0);
});

// --- processConstructorSolve ---

test('processConstructorSolve awards total_solves_100 at 100 completed solves', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 100,
    ]);

    $earned = app(AchievementService::class)->processConstructorSolve($constructor);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('total_solves_100');
});

test('processConstructorSolve awards total_solves_1000 at 1000 completed solves', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 1000,
    ]);

    $earned = app(AchievementService::class)->processConstructorSolve($constructor);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('total_solves_100')
        ->and($types)->toContain('total_solves_1000');
});

test('processConstructorSolve does not award below threshold', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 99,
    ]);

    $earned = app(AchievementService::class)->processConstructorSolve($constructor);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->not->toContain('total_solves_100');
});

test('processConstructorSolve sums across multiple published puzzles', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 60,
    ]);
    Crossword::factory()->for($constructor)->published()->create([
        'cached_completed_count' => 50,
    ]);

    $earned = app(AchievementService::class)->processConstructorSolve($constructor);

    $types = collect($earned)->pluck('type')->all();

    expect($types)->toContain('total_solves_100');
});

test('processConstructorSolve ignores draft puzzles', function () {
    $constructor = User::factory()->create();
    Crossword::factory()->for($constructor)->create([
        'cached_completed_count' => 200,
        'is_published' => false,
    ]);

    $earned = app(AchievementService::class)->processConstructorSolve($constructor);

    expect($earned)->toHaveCount(0);
});

// --- persistence ---

test('constructor achievements are persisted to the database', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->published()->create();

    app(AchievementService::class)->processPublish($user);

    $this->assertDatabaseHas('achievements', [
        'user_id' => $user->id,
        'type' => 'first_publish',
        'label' => 'First Creation',
        'icon' => 'pencil-square',
    ]);
});
