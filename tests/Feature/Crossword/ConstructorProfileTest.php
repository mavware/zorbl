<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
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

test('constructor profile displays bio when set', function () {
    $constructor = User::factory()->withBio('Crossword enthusiast since 2010.')->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('constructors.show', $constructor))
        ->assertOk()
        ->assertSee('Crossword enthusiast since 2010.');
});

test('constructor profile hides bio section when bio is null', function () {
    $constructor = User::factory()->create(['bio' => null]);
    $viewer = User::factory()->create();

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->assertDontSee('Tell solvers a little about yourself');
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

test('constructor profile shows attempt count per puzzle', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create(['title' => 'Popular Puzzle']);

    PuzzleAttempt::factory()->count(5)->create(['crossword_id' => $crossword->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->cached_attempts_count)->toBe(5);
});

test('constructor profile shows completion rate per puzzle', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create(['title' => 'Solvable Puzzle']);

    PuzzleAttempt::factory()->count(2)->completed()->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->count(3)->create(['crossword_id' => $crossword->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->cached_attempts_count)->toBe(5)
        ->and($puzzles->first()->cached_completed_count)->toBe(2);
});

test('constructor profile displays solved percentage text', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();

    PuzzleAttempt::factory()->count(3)->completed()->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->count(1)->create(['crossword_id' => $crossword->id]);

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->assertSee('75%')
        ->assertSee('solved');
});

test('constructor profile hides completion rate when no attempts', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->assertDontSee('solved');
});

test('constructor profile only shows published puzzles in puzzle cards', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create(['title' => 'Public Puzzle']);
    Crossword::factory()->for($constructor)->create(['title' => 'Secret Draft']);

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->assertSee('Public Puzzle')
        ->assertDontSee('Secret Draft');
});

// --- Sorting ---

test('puzzles default to newest first', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Older Puzzle',
        'created_at' => now()->subDays(5),
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Newer Puzzle',
        'created_at' => now(),
    ]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->title)->toBe('Newer Puzzle')
        ->and($puzzles->last()->title)->toBe('Older Puzzle');
});

test('puzzles can be sorted oldest first', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Older Puzzle',
        'created_at' => now()->subDays(5),
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Newer Puzzle',
        'created_at' => now(),
    ]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('sortBy', 'oldest');

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->title)->toBe('Older Puzzle')
        ->and($puzzles->last()->title)->toBe('Newer Puzzle');
});

test('puzzles can be sorted by most liked', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    $lessLiked = Crossword::factory()->published()->for($constructor)->create(['title' => 'Less Liked']);
    $mostLiked = Crossword::factory()->published()->for($constructor)->create(['title' => 'Most Liked']);

    CrosswordLike::factory()->count(5)->create(['crossword_id' => $mostLiked->id]);
    CrosswordLike::factory()->count(1)->create(['crossword_id' => $lessLiked->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('sortBy', 'most_liked');

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->title)->toBe('Most Liked');
});

test('puzzles can be sorted by most played', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    $lessPlayed = Crossword::factory()->published()->for($constructor)->create(['title' => 'Less Played']);
    $mostPlayed = Crossword::factory()->published()->for($constructor)->create(['title' => 'Most Played']);

    PuzzleAttempt::factory()->count(8)->create(['crossword_id' => $mostPlayed->id]);
    PuzzleAttempt::factory()->count(2)->create(['crossword_id' => $lessPlayed->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('sortBy', 'most_played');

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->title)->toBe('Most Played');
});

// --- Difficulty Filtering ---

test('puzzles can be filtered by difficulty', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Easy Puzzle',
        'difficulty_label' => 'Easy',
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Hard Puzzle',
        'difficulty_label' => 'Hard',
    ]);

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('difficulty', 'Easy')
        ->assertSee('Easy Puzzle')
        ->assertDontSee('Hard Puzzle');
});

test('clearing difficulty filter shows all puzzles', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Easy Puzzle',
        'difficulty_label' => 'Easy',
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Hard Puzzle',
        'difficulty_label' => 'Hard',
    ]);

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('difficulty', 'Easy')
        ->set('difficulty', '')
        ->assertSee('Easy Puzzle')
        ->assertSee('Hard Puzzle');
});

test('difficulty filter shows contextual empty state', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Easy Puzzle',
        'difficulty_label' => 'Easy',
    ]);

    Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('difficulty', 'Expert')
        ->assertSee('No puzzles match this difficulty.');
});

test('sort and difficulty filter work together', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    $easyOld = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Easy Old',
        'difficulty_label' => 'Easy',
        'created_at' => now()->subDays(5),
    ]);
    $easyNew = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Easy New',
        'difficulty_label' => 'Easy',
        'created_at' => now(),
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Hard Puzzle',
        'difficulty_label' => 'Hard',
    ]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor])
        ->set('difficulty', 'Easy')
        ->set('sortBy', 'oldest');

    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles)->toHaveCount(2)
        ->and($puzzles->first()->title)->toBe('Easy Old')
        ->and($puzzles->last()->title)->toBe('Easy New');

    $component->assertDontSee('Hard Puzzle');
});

// --- Profile Summary Stats ---

test('constructor profile displays total likes', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();

    CrosswordLike::factory()->count(7)->create(['crossword_id' => $crossword->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('totalLikes'))->toBe(7);
});

test('constructor profile hides likes when zero', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('totalLikes'))->toBe(0);
});

test('constructor profile only counts likes on published puzzles', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    $published = Crossword::factory()->published()->for($constructor)->create();
    $draft = Crossword::factory()->for($constructor)->create(['is_published' => false]);

    CrosswordLike::factory()->count(3)->create(['crossword_id' => $published->id]);
    CrosswordLike::factory()->count(5)->create(['crossword_id' => $draft->id]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('totalLikes'))->toBe(3);
});

test('constructor profile displays average rating', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();

    PuzzleComment::factory()->create(['crossword_id' => $crossword->id, 'rating' => 4]);
    PuzzleComment::factory()->create(['crossword_id' => $crossword->id, 'rating' => 5]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('averageRating'))->toBe(4.5)
        ->and($component->get('reviewCount'))->toBe(2);
});

test('constructor profile returns null average rating with no reviews', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create();

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('averageRating'))->toBeNull()
        ->and($component->get('reviewCount'))->toBe(0);
});

test('constructor profile ignores comments without ratings in average', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($constructor)->create();

    PuzzleComment::factory()->create(['crossword_id' => $crossword->id, 'rating' => 4]);
    PuzzleComment::factory()->create(['crossword_id' => $crossword->id, 'rating' => null]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('averageRating'))->toBe(4.0)
        ->and($component->get('reviewCount'))->toBe(1);
});

test('constructor profile displays average solve time', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create(['cached_avg_solve_time' => 120]);
    Crossword::factory()->published()->for($constructor)->create(['cached_avg_solve_time' => 180]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('averageSolveTime'))->toBe(150);
});

test('constructor profile returns null average solve time when no data', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();
    Crossword::factory()->published()->for($constructor)->create(['cached_avg_solve_time' => null]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('averageSolveTime'))->toBeNull();
});

test('constructor profile skips puzzles without solve times in average', function () {
    $constructor = User::factory()->create();
    $viewer = User::factory()->create();

    Crossword::factory()->published()->for($constructor)->create(['cached_avg_solve_time' => 200]);
    Crossword::factory()->published()->for($constructor)->create(['cached_avg_solve_time' => null]);

    $component = Livewire\Livewire::actingAs($viewer)
        ->test('pages::constructors.show', ['constructor' => $constructor]);

    expect($component->get('averageSolveTime'))->toBe(200);
});
