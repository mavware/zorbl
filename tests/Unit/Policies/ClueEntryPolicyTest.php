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
    $nonAdmin = Mockery::mock(User::class)->makePartial();
    $nonAdmin->id = 2;
    $nonAdmin->shouldReceive('hasRole')->with('Admin')->andReturnFalse();

    expect($this->policy->update($nonAdmin, makeClueEntry(1)))->toBeFalse();
});

test('update allows admins who are not the owner', function () {
    $admin = Mockery::mock(User::class)->makePartial();
    $admin->id = 2;
    $admin->shouldReceive('hasRole')->with('Admin')->andReturnTrue();

    expect($this->policy->update($admin, makeClueEntry(1)))->toBeTrue();
});

test('delete allows the owner', function () {
    expect($this->policy->delete($this->owner, makeClueEntry(1)))->toBeTrue();
});

test('delete denies non-owners', function () {
    $nonAdmin = Mockery::mock(User::class)->makePartial();
    $nonAdmin->id = 2;
    $nonAdmin->shouldReceive('hasRole')->with('Admin')->andReturnFalse();

    expect($this->policy->delete($nonAdmin, makeClueEntry(1)))->toBeFalse();
});

test('delete allows admins who are not the owner', function () {
    $admin = Mockery::mock(User::class)->makePartial();
    $admin->id = 2;
    $admin->shouldReceive('hasRole')->with('Admin')->andReturnTrue();

    expect($this->policy->delete($admin, makeClueEntry(1)))->toBeTrue();
});
