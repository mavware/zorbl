<?php

use App\Models\Crossword;
use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewPuzzlePublished;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('followers are notified when a constructor publishes a puzzle', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $follower1 = User::factory()->create();
    $follower2 = User::factory()->create();

    Follow::factory()->create(['follower_id' => $follower1->id, 'following_id' => $constructor->id]);
    Follow::factory()->create(['follower_id' => $follower2->id, 'following_id' => $constructor->id]);

    $crossword = Crossword::factory()->for($constructor)->create();

    $this->actingAs($constructor);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true);

    Notification::assertSentTo([$follower1, $follower2], NewPuzzlePublished::class);
});

test('followers are not notified when a constructor unpublishes a puzzle', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $follower = User::factory()->create();

    Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $constructor->id]);

    $crossword = Crossword::factory()->published()->for($constructor)->create();

    $this->actingAs($constructor);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', false);

    Notification::assertNothingSent();
});

test('no notifications sent when constructor has no followers', function () {
    Notification::fake();

    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->create();

    $this->actingAs($constructor);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true);

    Notification::assertNothingSent();
});

test('notification contains correct data', function () {
    $constructor = User::factory()->create(['name' => 'Jane Doe']);
    $crossword = Crossword::factory()->for($constructor)->create(['title' => 'My Great Puzzle']);

    $notification = new NewPuzzlePublished($crossword, $constructor);
    $data = $notification->toArray($constructor);

    expect($data)
        ->type->toBe('puzzle.published')
        ->title->toContain('Jane Doe')
        ->title->toContain('My Great Puzzle')
        ->url->toBe(route('crosswords.solver', $crossword))
        ->crossword_id->toBe($crossword->id)
        ->constructor_id->toBe($constructor->id);
});

test('notification uses database channel', function () {
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->create();

    $notification = new NewPuzzlePublished($crossword, $constructor);

    expect($notification->via($constructor))->toBe(['database']);
});
