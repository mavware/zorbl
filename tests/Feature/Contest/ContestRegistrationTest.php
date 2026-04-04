<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use Livewire\Livewire;

test('user can register for an active contest', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->call('register');

    expect(ContestEntry::where('user_id', $user->id)->where('contest_id', $contest->id)->exists())->toBeTrue();
});

test('user can register for an upcoming contest', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->upcoming()->create();

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->call('register');

    expect(ContestEntry::where('user_id', $user->id)->where('contest_id', $contest->id)->exists())->toBeTrue();
});

test('duplicate registration returns existing entry', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    ContestEntry::factory()->for($contest)->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->call('register');

    expect(ContestEntry::where('user_id', $user->id)->where('contest_id', $contest->id)->count())->toBe(1);
});
