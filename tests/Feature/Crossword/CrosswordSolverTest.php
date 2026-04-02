<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('owner can solve their own puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSet('isOwner', true);

    // PuzzleAttempt should be created automatically
    expect(PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();
});

test('user can solve a published puzzle by another creator', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSet('isOwner', false);
});

test('user cannot solve an unpublished puzzle by another creator', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertForbidden();
});

test('solver saves progress to puzzle attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);
    $progress[0][0] = 'A';

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->progress[0][0])->toBe('A')
        ->and($attempt->is_completed)->toBeFalse();
});

test('solver marks attempt as completed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, true);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->is_completed)->toBeTrue();
});

test('solver resumes existing attempt progress', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $progress = Crossword::emptySolution(3, 3);
    $progress[1][1] = 'X';

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'progress' => $progress,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('progress.1.1', 'X');
});

test('solver hides edit button for non-owners', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('Edit');
});

test('solver shows edit button for owners', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('Edit');
});
