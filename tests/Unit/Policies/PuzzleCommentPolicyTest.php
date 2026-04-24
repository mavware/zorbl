<?php

use App\Models\PuzzleComment;
use App\Models\User;
use App\Policies\PuzzleCommentPolicy;

beforeEach(function () {
    $this->policy = new PuzzleCommentPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->otherUser = new User;
    $this->otherUser->id = 2;
});

function makeComment(int $ownerId): PuzzleComment
{
    $comment = new PuzzleComment;
    $comment->user_id = $ownerId;

    return $comment;
}

test('create allows any authenticated user', function () {
    expect($this->policy->create($this->owner))->toBeTrue();
});

test('delete allows the owner', function () {
    expect($this->policy->delete($this->owner, makeComment(1)))->toBeTrue();
});

test('delete denies non-owners', function () {
    expect($this->policy->delete($this->otherUser, makeComment(1)))->toBeFalse();
});
