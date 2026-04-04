<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use Livewire\Livewire;

test('correct meta answer marks entry as solved', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['meta_answer' => 'PUZZLE']);
    $entry = ContestEntry::factory()->for($contest)->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->set('metaAnswer', 'puzzle')
        ->call('submitMetaAnswer')
        ->assertSet('metaSuccess', true);

    expect($entry->fresh()->meta_solved)->toBeTrue();
});

test('incorrect meta answer does not mark entry as solved', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['meta_answer' => 'PUZZLE']);
    $entry = ContestEntry::factory()->for($contest)->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->set('metaAnswer', 'wrong')
        ->call('submitMetaAnswer')
        ->assertSet('metaSuccess', false);

    expect($entry->fresh()->meta_solved)->toBeFalse()
        ->and($entry->fresh()->meta_attempts_count)->toBe(1);
});

test('meta answer normalizes spaces and special characters', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['meta_answer' => 'HELLO WORLD']);
    $entry = ContestEntry::factory()->for($contest)->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->set('metaAnswer', 'hello-world!')
        ->call('submitMetaAnswer')
        ->assertSet('metaSuccess', true);
});

test('attempt limit is enforced', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create([
        'meta_answer' => 'ANSWER',
        'max_meta_attempts' => 2,
    ]);
    $entry = ContestEntry::factory()->for($contest)->for($user)->create([
        'meta_attempts_count' => 2,
    ]);

    Livewire::actingAs($user)
        ->test('pages::contests.show', ['contest' => $contest])
        ->set('metaAnswer', 'answer')
        ->call('submitMetaAnswer')
        ->assertForbidden();
});
