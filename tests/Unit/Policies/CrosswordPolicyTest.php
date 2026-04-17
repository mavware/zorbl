<?php

use App\Models\Crossword;
use App\Models\User;
use App\Policies\CrosswordPolicy;

beforeEach(function () {
    $this->policy = new CrosswordPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->otherUser = new User;
    $this->otherUser->id = 2;
});

function makeCrossword(int $ownerId, bool $published = false): Crossword
{
    $crossword = new Crossword;
    $crossword->user_id = $ownerId;
    $crossword->is_published = $published;

    return $crossword;
}

test('viewAny allows any authenticated user', function () {
    expect($this->policy->viewAny($this->owner))->toBeTrue();
});

test('view allows the owner regardless of published status', function () {
    expect($this->policy->view($this->owner, makeCrossword(1, false)))->toBeTrue();
});

test('view allows other users for published crosswords', function () {
    expect($this->policy->view($this->otherUser, makeCrossword(1, true)))->toBeTrue();
});

test('view denies other users for unpublished crosswords', function () {
    expect($this->policy->view($this->otherUser, makeCrossword(1, false)))->toBeFalse();
});

test('solve allows the owner regardless of published status', function () {
    expect($this->policy->solve($this->owner, makeCrossword(1, false)))->toBeTrue();
});

test('solve allows other users for published crosswords', function () {
    expect($this->policy->solve($this->otherUser, makeCrossword(1, true)))->toBeTrue();
});

test('solve denies other users for unpublished crosswords', function () {
    expect($this->policy->solve($this->otherUser, makeCrossword(1, false)))->toBeFalse();
});

test('create allows any authenticated user', function () {
    expect($this->policy->create($this->owner))->toBeTrue();
});

test('update allows the owner', function () {
    expect($this->policy->update($this->owner, makeCrossword(1)))->toBeTrue();
});

test('update denies non-owners', function () {
    expect($this->policy->update($this->otherUser, makeCrossword(1)))->toBeFalse();
});

test('delete allows the owner', function () {
    expect($this->policy->delete($this->owner, makeCrossword(1)))->toBeTrue();
});

test('delete denies non-owners', function () {
    expect($this->policy->delete($this->otherUser, makeCrossword(1)))->toBeFalse();
});
