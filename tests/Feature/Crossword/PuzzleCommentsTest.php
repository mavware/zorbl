<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;

test('solver can submit a comment with rating after solving', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('commentBody', 'Great puzzle!')
        ->set('commentRating', 4)
        ->call('submitComment');

    $comment = PuzzleComment::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($comment)->not->toBeNull()
        ->and($comment->body)->toBe('Great puzzle!')
        ->and($comment->rating)->toBe(4);
});

test('comment requires a body', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('commentBody', '')
        ->call('submitComment')
        ->assertHasErrors(['commentBody' => 'required']);
});

test('user can delete their own comment', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleComment::create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'body' => 'Nice puzzle',
        'rating' => 5,
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('deleteComment');

    expect(PuzzleComment::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('comment without rating is allowed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('commentBody', 'No rating comment')
        ->set('commentRating', 0)
        ->call('submitComment');

    $comment = PuzzleComment::where('user_id', $user->id)->first();
    expect($comment->body)->toBe('No rating comment')
        ->and($comment->rating)->toBeNull();
});

test('duplicate comment updates existing one', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleComment::create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'body' => 'Original comment',
        'rating' => 3,
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('commentBody', 'Updated comment')
        ->set('commentRating', 5)
        ->call('submitComment');

    expect(PuzzleComment::where('user_id', $user->id)->where('crossword_id', $crossword->id)->count())->toBe(1);
    $comment = PuzzleComment::where('user_id', $user->id)->first();
    expect($comment->body)->toBe('Updated comment')
        ->and($comment->rating)->toBe(5);
});

test('average rating is calculated correctly', function () {
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleComment::factory()->for($crossword)->create(['rating' => 4]);
    PuzzleComment::factory()->for($crossword)->create(['rating' => 2]);

    $avg = PuzzleComment::where('crossword_id', $crossword->id)
        ->whereNotNull('rating')
        ->where('rating', '>', 0)
        ->avg('rating');

    expect(round($avg, 1))->toBe(3.0);
});

test('comments section is visible when puzzle is solved', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($user)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
    ]);

    $this->actingAs($user);

    Livewire\Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('isSolved', true)
        ->assertSee('Comments');
});
