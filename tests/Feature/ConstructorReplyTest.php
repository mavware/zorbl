<?php

use App\Http\Resources\Api\V1\PuzzleCommentResource;
use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use Carbon\CarbonImmutable;

test('constructor reply fields can be stored on a comment', function () {
    $constructor = User::factory()->create();

    $crossword = Crossword::factory()->published()->for($constructor, 'user')->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $comment = PuzzleComment::create([
        'user_id' => User::factory()->create()->id,
        'crossword_id' => $crossword->id,
        'body' => 'Fun puzzle!',
        'rating' => 4,
    ]);

    $comment->update([
        'constructor_reply' => 'Thanks for solving!',
        'constructor_reply_at' => now(),
    ]);

    $comment->refresh();

    expect($comment->constructor_reply)->toBe('Thanks for solving!')
        ->and($comment->constructor_reply_at)->not->toBeNull();
});

test('constructor reply fields are nullable by default', function () {
    $comment = PuzzleComment::factory()->create();

    expect($comment->constructor_reply)->toBeNull()
        ->and($comment->constructor_reply_at)->toBeNull();
});

test('factory withReply state sets reply fields', function () {
    $comment = PuzzleComment::factory()->withReply()->create();

    expect($comment->constructor_reply)->not->toBeNull()
        ->and($comment->constructor_reply)->toBeString()
        ->and($comment->constructor_reply_at)->not->toBeNull();
});

test('constructor reply can be deleted by setting to null', function () {
    $comment = PuzzleComment::factory()->withReply()->create();

    expect($comment->constructor_reply)->not->toBeNull();

    $comment->update([
        'constructor_reply' => null,
        'constructor_reply_at' => null,
    ]);

    $comment->refresh();

    expect($comment->constructor_reply)->toBeNull()
        ->and($comment->constructor_reply_at)->toBeNull();
});

test('constructor_reply_at is cast to datetime', function () {
    $comment = PuzzleComment::factory()->withReply()->create();

    expect($comment->constructor_reply_at)->toBeInstanceOf(CarbonImmutable::class);
});

test('api resource includes constructor reply fields', function () {
    $comment = PuzzleComment::factory()->withReply()->create();

    $resource = new PuzzleCommentResource($comment);
    $data = $resource->toArray(request());

    expect($data['attributes'])->toHaveKeys(['constructor_reply', 'constructor_reply_at'])
        ->and($data['attributes']['constructor_reply'])->toBe($comment->constructor_reply);
});

test('api resource returns null reply fields when no reply exists', function () {
    $comment = PuzzleComment::factory()->create();

    $resource = new PuzzleCommentResource($comment);
    $data = $resource->toArray(request());

    expect($data['attributes']['constructor_reply'])->toBeNull()
        ->and($data['attributes']['constructor_reply_at'])->toBeNull();
});

test('constructor reply is included in comment fillable', function () {
    $comment = new PuzzleComment;

    expect($comment->getFillable())
        ->toContain('constructor_reply')
        ->toContain('constructor_reply_at');
});
