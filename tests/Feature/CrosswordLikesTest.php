<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\User;
use Livewire\Livewire;

test('users can like a crossword from a puzzle card', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-card', ['crossword' => $crossword])
        ->call('toggleLike');

    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();
});

test('users can unlike a crossword from a puzzle card', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-card', ['crossword' => $crossword, 'isLiked' => true])
        ->call('toggleLike');

    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('like count is displayed on puzzle cards', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Likeable Puzzle']);

    CrosswordLike::factory()->count(3)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Likeable Puzzle');
});

test('users can like a crossword from the solver page', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('toggleLike');

    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();
});

test('users can unlike a crossword from the solver page', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('toggleLike');

    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('solver page shows like count', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    CrosswordLike::factory()->count(5)->create(['crossword_id' => $crossword->id]);

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    expect($component->get('likesCount'))->toBe(5);
});

test('users cannot like the same crossword twice', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-card', ['crossword' => $crossword])
        ->call('toggleLike')
        ->call('toggleLike');

    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->count())->toBe(0);
});

test('liking is togglable - double toggle removes the like', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword]);

    $component->call('toggleLike');
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();

    $component->call('toggleLike');
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('deleting a crossword cascades to its likes', function () {
    $crossword = Crossword::factory()->published()->create();
    CrosswordLike::factory()->count(3)->create(['crossword_id' => $crossword->id]);

    $crossword->delete();

    expect(CrosswordLike::where('crossword_id', $crossword->id)->count())->toBe(0);
});

test('deleting a user cascades to their likes', function () {
    $user = User::factory()->create();
    CrosswordLike::factory()->count(3)->create(['user_id' => $user->id]);

    $user->delete();

    expect(CrosswordLike::where('user_id', $user->id)->count())->toBe(0);
});
