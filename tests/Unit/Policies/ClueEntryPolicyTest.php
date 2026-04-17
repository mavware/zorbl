<?php

use App\Models\ClueEntry;
use App\Models\User;
use App\Policies\ClueEntryPolicy;

beforeEach(function () {
    $this->policy = new ClueEntryPolicy;

    $this->owner = new User;
    $this->owner->id = 1;

    $this->otherUser = new User;
    $this->otherUser->id = 2;
});

function makeClueEntry(int $ownerId): ClueEntry
{
    $entry = new ClueEntry;
    $entry->user_id = $ownerId;

    return $entry;
}

test('viewAny allows any authenticated user', function () {
    expect($this->policy->viewAny($this->owner))->toBeTrue();
});

test('create allows any authenticated user', function () {
    expect($this->policy->create($this->owner))->toBeTrue();
});

test('update allows the owner', function () {
    expect($this->policy->update($this->owner, makeClueEntry(1)))->toBeTrue();
});

test('update denies non-owners', function () {
    expect($this->policy->update($this->otherUser, makeClueEntry(1)))->toBeFalse();
});

test('delete allows the owner', function () {
    expect($this->policy->delete($this->owner, makeClueEntry(1)))->toBeTrue();
});

test('delete denies non-owners', function () {
    expect($this->policy->delete($this->otherUser, makeClueEntry(1)))->toBeFalse();
});
