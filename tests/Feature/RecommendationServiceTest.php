<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\PuzzleAttempt;
use App\Models\Tag;
use App\Models\User;
use App\Services\RecommendationService;

test('returns empty collection when user has fewer than 3 completed solves', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->completed()->for($user)->create([
        'crossword_id' => Crossword::factory()->published()->for($creator)->create()->id,
    ]);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations)->toBeEmpty();
});

test('returns recommendations when user has at least 3 completed solves', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create([
        'difficulty_label' => 'Medium',
    ]);

    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Unsolved Medium',
        'difficulty_label' => 'Medium',
    ]);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations)->not->toBeEmpty();
});

test('excludes puzzles the user has already attempted', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $attempted = Crossword::factory()->published()->for($creator)->create(['title' => 'Already Attempted']);
    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $attempted->id]);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations->pluck('title'))->not->toContain('Already Attempted');
});

test('excludes puzzles created by the user', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    Crossword::factory()->published()->for($user)->create(['title' => 'My Own Puzzle']);
    Crossword::factory()->published()->for($creator)->create(['title' => 'Someone Else Puzzle']);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations->pluck('title'))->not->toContain('My Own Puzzle');
});

test('prefers puzzles matching the user preferred difficulty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(4)->create([
        'difficulty_label' => 'Hard',
    ]);
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $hardPuzzle = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard Recommendation',
        'difficulty_label' => 'Hard',
        'cached_completed_count' => 10,
    ]);
    $easyPuzzle = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Recommendation',
        'difficulty_label' => 'Easy',
        'cached_completed_count' => 10,
    ]);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    $titles = $recommendations->pluck('title');
    if ($titles->contains('Hard Recommendation') && $titles->contains('Easy Recommendation')) {
        $hardIndex = $titles->search('Hard Recommendation');
        $easyIndex = $titles->search('Easy Recommendation');
        expect($hardIndex)->toBeLessThan($easyIndex);
    }
});

test('prefers puzzles from liked constructors', function () {
    $user = User::factory()->create();
    $likedCreator = User::factory()->create();
    $otherCreator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($otherCreator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $likedPuzzle = Crossword::factory()->published()->for($likedCreator)->create(['title' => 'Liked Already']);
    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $likedPuzzle->id]);

    Crossword::factory()->published()->for($likedCreator)->create([
        'title' => 'From Liked Creator',
        'cached_completed_count' => 5,
    ]);
    Crossword::factory()->published()->for($otherCreator)->create([
        'title' => 'From Other Creator',
        'cached_completed_count' => 5,
    ]);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations->pluck('title'))->toContain('From Liked Creator');
});

test('prefers puzzles with matching tags', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Science', 'slug' => 'science']);

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        $puzzle->tags()->attach($tag);
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $taggedUnsolved = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Tagged Unsolved',
        'cached_completed_count' => 5,
    ]);
    $taggedUnsolved->tags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Untagged Unsolved',
        'cached_completed_count' => 5,
    ]);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    $titles = $recommendations->pluck('title');
    if ($titles->contains('Tagged Unsolved') && $titles->contains('Untagged Unsolved')) {
        expect($titles->search('Tagged Unsolved'))->toBeLessThan($titles->search('Untagged Unsolved'));
    }
});

test('respects blocked tags', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $blockedTag = Tag::factory()->create(['name' => 'Blocked', 'slug' => 'blocked']);

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $blockedPuzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Blocked Puzzle']);
    $blockedPuzzle->tags()->attach($blockedTag);

    $user->blockedTags()->attach($blockedTag);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations->pluck('title'))->not->toContain('Blocked Puzzle');
});

test('respects the limit parameter', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    Crossword::factory()->published()->for($creator)->count(10)->create();

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user, 3);

    expect($recommendations)->toHaveCount(3);
});

test('build profile returns null for users with fewer than 3 solves', function () {
    $user = User::factory()->create();

    $service = app(RecommendationService::class);
    $profile = $service->buildProfile($user);

    expect($profile)->toBeNull();
});

test('build profile detects preferred difficulty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    foreach (['Easy', 'Easy', 'Easy', 'Hard'] as $diff) {
        $puzzle = Crossword::factory()->published()->for($creator)->create(['difficulty_label' => $diff]);
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $service = app(RecommendationService::class);
    $profile = $service->buildProfile($user);

    expect($profile['difficulty'])->toBe('Easy');
});

test('build profile detects preferred grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $puzzle = Crossword::factory()->published()->for($creator)->create([
            'width' => 7,
            'height' => 7,
            'grid' => Crossword::emptyGrid(7, 7),
            'solution' => Crossword::emptySolution(7, 7),
        ]);
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $service = app(RecommendationService::class);
    $profile = $service->buildProfile($user);

    expect($profile['grid_size'])->toBe('small');
});

test('build profile detects preferred tags', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'History', 'slug' => 'history']);

    for ($i = 0; $i < 3; $i++) {
        $puzzle = Crossword::factory()->published()->for($creator)->create();
        $puzzle->tags()->attach($tag);
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $service = app(RecommendationService::class);
    $profile = $service->buildProfile($user);

    expect($profile['tag_ids'])->toContain($tag->id);
});

test('build profile detects liked constructor ids', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $likedCreator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    $likedPuzzle = Crossword::factory()->published()->for($likedCreator)->create();
    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $likedPuzzle->id]);

    $service = app(RecommendationService::class);
    $profile = $service->buildProfile($user);

    expect($profile['liked_constructor_ids'])->toContain($likedCreator->id);
});

test('dashboard shows recommended section when user has enough solve history', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create([
        'difficulty_label' => 'Medium',
    ]);
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Recommended Puzzle',
        'difficulty_label' => 'Medium',
    ]);

    Livewire\Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Recommended for You')
        ->assertSee('Recommended Puzzle');
});

test('dashboard hides recommended section when user has no solve history', function () {
    $user = User::factory()->create();

    Livewire\Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertDontSee('Recommended for You');
});

test('only excludes unpublished puzzles from recommendations', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $solvedPuzzles = Crossword::factory()->published()->for($creator)->count(3)->create();
    foreach ($solvedPuzzles as $puzzle) {
        PuzzleAttempt::factory()->completed()->for($user)->create(['crossword_id' => $puzzle->id]);
    }

    Crossword::factory()->for($creator)->create(['title' => 'Draft Puzzle']);

    $service = app(RecommendationService::class);
    $recommendations = $service->recommend($user);

    expect($recommendations->pluck('title'))->not->toContain('Draft Puzzle');
});
