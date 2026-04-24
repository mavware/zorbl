<?php

use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

// --- User Model Relationship ---

test('user can have blocked tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $user->blockedTags()->attach($tag);

    expect($user->blockedTags)->toHaveCount(1)
        ->and($user->blockedTags->first()->id)->toBe($tag->id);
});

test('blocked tags relationship includes timestamps', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $user->blockedTags()->attach($tag);

    $pivot = $user->blockedTags()->first()->pivot;
    expect($pivot->created_at)->not->toBeNull()
        ->and($pivot->updated_at)->not->toBeNull();
});

test('deleting user cascades blocked tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $user->blockedTags()->attach($tag);

    $user->delete();

    $this->assertDatabaseMissing('blocked_tags', ['user_id' => $user->id]);
});

test('deleting tag cascades blocked tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    $user->blockedTags()->attach($tag);

    $tag->delete();

    $this->assertDatabaseMissing('blocked_tags', ['tag_id' => $tag->id]);
});

// --- Blocked Tags Settings Component ---

test('blocked tags component loads for authenticated user', function () {
    $user = User::factory()->create();
    Tag::factory()->create(['name' => 'Sports']);

    Livewire::actingAs($user)
        ->test('pages::settings.blocked-tags')
        ->assertOk()
        ->assertSee('Blocked tags')
        ->assertSee('Sports');
});

test('user can toggle a tag to blocked', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Cryptic']);

    Livewire::actingAs($user)
        ->test('pages::settings.blocked-tags')
        ->call('toggleTag', $tag->id)
        ->assertDispatched('blocked-tags-updated');

    expect($user->fresh()->blockedTags)->toHaveCount(1)
        ->and($user->fresh()->blockedTags->first()->id)->toBe($tag->id);
});

test('user can unblock a previously blocked tag', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Cryptic']);
    $user->blockedTags()->attach($tag);

    Livewire::actingAs($user)
        ->test('pages::settings.blocked-tags')
        ->assertSet('blockedTagIds', [$tag->id])
        ->call('toggleTag', $tag->id)
        ->assertDispatched('blocked-tags-updated');

    expect($user->fresh()->blockedTags)->toHaveCount(0);
});

test('blocked tags count is displayed', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->count(3)->create();
    $user->blockedTags()->attach($tags->pluck('id'));

    Livewire::actingAs($user)
        ->test('pages::settings.blocked-tags')
        ->assertSee('3 tags blocked');
});

test('blocked tags section is shown on profile settings page', function () {
    $user = User::factory()->create();
    Tag::factory()->create(['name' => 'Mini']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Blocked tags');
});

// --- Puzzle Browse Filtering ---

test('blocked tags hide puzzles from browse for authenticated user', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Cryptic']);
    $creator = User::factory()->create();

    $blockedPuzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Hidden Cryptic']);
    $blockedPuzzle->tags()->attach($tag);

    $visiblePuzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Visible Puzzle']);

    $user->blockedTags()->attach($tag);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->assertDontSee('Hidden Cryptic')
        ->assertSee('Visible Puzzle');
});

test('puzzles with multiple tags are hidden if any tag is blocked', function () {
    $user = User::factory()->create();
    $blockedTag = Tag::factory()->create(['name' => 'Blocked']);
    $otherTag = Tag::factory()->create(['name' => 'Other']);
    $creator = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Multi-Tag Puzzle']);
    $puzzle->tags()->attach([$blockedTag->id, $otherTag->id]);

    $user->blockedTags()->attach($blockedTag);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->assertDontSee('Multi-Tag Puzzle');
});

test('guests are not affected by blocked tags', function () {
    $tag = Tag::factory()->create(['name' => 'Cryptic']);
    $creator = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Tagged Puzzle']);
    $puzzle->tags()->attach($tag);

    Livewire::test('pages::puzzles.index')
        ->assertSee('Tagged Puzzle');
});

test('untagged puzzles are not affected by blocked tags', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Blocked']);
    $creator = User::factory()->create();

    $user->blockedTags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create(['title' => 'No Tags Puzzle']);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->assertSee('No Tags Puzzle');
});

test('puzzles with non-blocked tags remain visible', function () {
    $user = User::factory()->create();
    $blockedTag = Tag::factory()->create(['name' => 'Blocked']);
    $safeTag = Tag::factory()->create(['name' => 'Safe']);
    $creator = User::factory()->create();

    $user->blockedTags()->attach($blockedTag);

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Safe Puzzle']);
    $puzzle->tags()->attach($safeTag);

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->assertSee('Safe Puzzle');
});

// --- API Filtering ---

test('api excludes puzzles with blocked tags for authenticated user', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Cryptic']);

    $blockedPuzzle = Crossword::factory()->published()->create(['title' => 'API Hidden']);
    $blockedPuzzle->tags()->attach($tag);

    $visiblePuzzle = Crossword::factory()->published()->create(['title' => 'API Visible']);

    $user->blockedTags()->attach($tag);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/crosswords');

    $response->assertSuccessful();

    $titles = collect($response->json('data'))->pluck('attributes.title');
    expect($titles)->not->toContain('API Hidden')
        ->and($titles)->toContain('API Visible');
});

test('api shows all puzzles for unauthenticated user', function () {
    $tag = Tag::factory()->create(['name' => 'Cryptic']);

    $puzzle = Crossword::factory()->published()->create(['title' => 'Public Puzzle']);
    $puzzle->tags()->attach($tag);

    $response = $this->getJson('/api/v1/crosswords');

    $response->assertSuccessful();

    $titles = collect($response->json('data'))->pluck('attributes.title');
    expect($titles)->toContain('Public Puzzle');
});
