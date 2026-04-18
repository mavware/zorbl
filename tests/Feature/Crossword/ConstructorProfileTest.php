<?php

use App\Models\Crossword;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
use Illuminate\Support\Facades\Notification;

test('constructor profile page displays user info and puzzles', function () {
    $constructor = User::factory()->create(['name' => 'Jane Constructor']);
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create(['title' => 'My Great Puzzle']);

    $this->actingAs($viewer)
        ->get(route('constructors.show', $constructor))
        ->assertOk()
        ->assertSee('Jane Constructor')
        ->assertSee('My Great Puzzle');
});

test('constructor profile shows empty state when no puzzles', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('constructors.show', $constructor))
        ->assertOk()
        ->assertSee('No published puzzles yet');
});

test('user can follow a constructor', function () {
    $constructor = User::factory()->create();
    $follower = User::factory()->create();

    $this->actingAs($follower);

    Livewire\Livewire::test('pages::constructors.show', ['constructor' => $constructor])
        ->call('toggleFollow');

    expect(Follow::where('follower_id', $follower->id)->where('following_id', $constructor->id)->exists())->toBeTrue();
});

test('user can unfollow a constructor', function () {
    $constructor = User::factory()->create();
    $follower = User::factory()->create();

    Follow::create(['follower_id' => $follower->id, 'following_id' => $constructor->id]);

    $this->actingAs($follower);

    Livewire\Livewire::test('pages::constructors.show', ['constructor' => $constructor])
        ->call('toggleFollow');

    expect(Follow::where('follower_id', $follower->id)->where('following_id', $constructor->id)->exists())->toBeFalse();
});

test('user cannot follow themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire\Livewire::test('pages::constructors.show', ['constructor' => $user])
        ->call('toggleFollow');

    expect(Follow::where('follower_id', $user->id)->count())->toBe(0);
});

test('follower count is displayed correctly', function () {
    $constructor = User::factory()->create();
    $followers = User::factory()->count(3)->create();

    foreach ($followers as $follower) {
        Follow::create(['follower_id' => $follower->id, 'following_id' => $constructor->id]);
    }

    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('constructors.show', $constructor))
        ->assertOk()
        ->assertSee('3 followers');
});

test('solver page links to constructor profile', function () {
    $constructor = User::factory()->create(['name' => 'Puzzle Master']);
    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $solver = User::factory()->create();

    $this->actingAs($solver)
        ->get(route('crosswords.solver', $crossword))
        ->assertOk()
        ->assertSee('Puzzle Master');
});

test('following a constructor sends a notification', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $follower = User::factory()->create();

    $this->actingAs($follower);

    Livewire\Livewire::test('pages::constructors.show', ['constructor' => $constructor])
        ->call('toggleFollow');

    Notification::assertSentTo($constructor, NewFollower::class);
});

test('unfollowing does not send a notification', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $follower = User::factory()->create();

    Follow::create(['follower_id' => $follower->id, 'following_id' => $constructor->id]);

    $this->actingAs($follower);

    Livewire\Livewire::test('pages::constructors.show', ['constructor' => $constructor])
        ->call('toggleFollow');

    Notification::assertNotSentTo($constructor, NewFollower::class);
});

test('unauthenticated user cannot access constructor profile', function () {
    $constructor = User::factory()->create();

    $this->get(route('constructors.show', $constructor))
        ->assertRedirect();
});
