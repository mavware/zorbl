<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

test('similar puzzles are shown when puzzle is solved', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();
    Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('You Might Also Enjoy');
});

test('similar puzzles are hidden when puzzle is not solved', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();
    Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('You Might Also Enjoy');
});

test('similar puzzles excludes already solved puzzles', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Current Puzzle']);
    $solved = Crossword::factory()->published()->for($creator)->create(['title' => 'Already Solved']);
    $unsolved = Crossword::factory()->published()->for($creator)->create(['title' => 'Fresh Puzzle']);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $solved->id,
        'solve_time_seconds' => 100,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);
    $similar = $component->get('similarPuzzles');

    expect($similar->pluck('id')->toArray())
        ->toContain($unsolved->id)
        ->not->toContain($solved->id)
        ->not->toContain($crossword->id);
});

test('similar puzzles prioritizes matching tags', function () {
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Sports']);

    $crossword = Crossword::factory()->published()->for($creator)->create();
    $crossword->tags()->attach($tag);

    $tagged = Crossword::factory()->published()->for($creator)->create(['title' => 'Tagged Match']);
    $tagged->tags()->attach($tag);

    $untagged = Crossword::factory()->published()->for($creator)->create(['title' => 'No Tags']);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);
    $similar = $component->get('similarPuzzles');

    expect($similar->first()->id)->toBe($tagged->id);
});

test('similar puzzles limits to 4 results', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Crossword::factory()->published()->for($creator)->count(10)->create();

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    expect($component->get('similarPuzzles'))->toHaveCount(4);
});

test('similar puzzles excludes unpublished puzzles', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $published = Crossword::factory()->published()->for($creator)->create(['title' => 'Published']);
    Crossword::factory()->for($creator)->create(['title' => 'Draft']);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);
    $similar = $component->get('similarPuzzles');

    expect($similar->pluck('id')->toArray())
        ->toContain($published->id);

    $similar->each(fn ($puzzle) => expect($puzzle->is_published)->toBeTrue());
});

test('similar puzzles respects blocked tags', function () {
    $creator = User::factory()->create();
    $blockedTag = Tag::factory()->create(['name' => 'Politics']);

    $crossword = Crossword::factory()->published()->for($creator)->create();
    $blocked = Crossword::factory()->published()->for($creator)->create(['title' => 'Blocked Puzzle']);
    $blocked->tags()->attach($blockedTag);

    $safe = Crossword::factory()->published()->for($creator)->create(['title' => 'Safe Puzzle']);

    $user = User::factory()->create();
    $user->blockedTags()->attach($blockedTag);

    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);
    $similar = $component->get('similarPuzzles');

    expect($similar->pluck('id')->toArray())
        ->toContain($safe->id)
        ->not->toContain($blocked->id);
});

test('similar puzzles returns empty when no other puzzles exist', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('You Might Also Enjoy');
});

test('similar puzzles fills with difficulty matches when tags are insufficient', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'difficulty_label' => 'Hard',
    ]);

    $hardPuzzle = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard One',
        'difficulty_label' => 'Hard',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy One',
        'difficulty_label' => 'Easy',
    ]);

    $user = User::factory()->create();
    PuzzleAttempt::factory()->completed()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);
    $similar = $component->get('similarPuzzles');

    expect($similar->first()->id)->toBe($hardPuzzle->id);
});
