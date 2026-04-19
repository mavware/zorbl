<?php

use App\Models\PuzzleAttempt;
use App\Models\User;
use App\Policies\PuzzleAttemptPolicy;

beforeEach(function () {
    $this->policy = new PuzzleAttemptPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->otherUser = new User;
    $this->otherUser->id = 2;
});

function makeAttempt(int $ownerId): PuzzleAttempt
{
    $attempt = new PuzzleAttempt;
    $attempt->user_id = $ownerId;

    return $attempt;
}

test('viewAny allows any authenticated user', function () {
    expect($this->policy->viewAny($this->owner))->toBeTrue();
});

test('view allows the attempt owner', function () {
    expect($this->policy->view($this->owner, makeAttempt(1)))->toBeTrue();
});

test('view denies non-owners', function () {
    expect($this->policy->view($this->otherUser, makeAttempt(1)))->toBeFalse();
});

test('update allows the attempt owner', function () {
    expect($this->policy->update($this->owner, makeAttempt(1)))->toBeTrue();
});

test('update denies non-owners', function () {
    expect($this->policy->update($this->otherUser, makeAttempt(1)))->toBeFalse();
});

test('delete allows the attempt owner', function () {
    expect($this->policy->delete($this->owner, makeAttempt(1)))->toBeTrue();
});

test('delete denies non-owners', function () {
    expect($this->policy->delete($this->otherUser, makeAttempt(1)))->toBeFalse();
});
