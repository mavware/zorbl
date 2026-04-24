<?php

use App\Models\FavoriteList;
use App\Models\User;
use App\Policies\FavoriteListPolicy;

beforeEach(function () {
    $this->policy = new FavoriteListPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->otherUser = new User;
    $this->otherUser->id = 2;
});

function makeFavoriteList(int $ownerId): FavoriteList
{
    $list = new FavoriteList;
    $list->user_id = $ownerId;

    return $list;
}

test('update allows the owner', function () {
    expect($this->policy->update($this->owner, makeFavoriteList(1)))->toBeTrue();
});

test('update denies non-owners', function () {
    expect($this->policy->update($this->otherUser, makeFavoriteList(1)))->toBeFalse();
});

test('delete allows the owner', function () {
    expect($this->policy->delete($this->owner, makeFavoriteList(1)))->toBeTrue();
});

test('delete denies non-owners', function () {
    expect($this->policy->delete($this->otherUser, makeFavoriteList(1)))->toBeFalse();
});
