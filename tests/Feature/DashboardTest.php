<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows published puzzle count', function () {
    $user = User::factory()->create();
    Crossword::factory()->count(2)->published()->create(['user_id' => $user->id]);
    Crossword::factory()->create(['user_id' => $user->id]); // draft

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('publishedCount'))->toBe(2);
});

test('dashboard shows draft puzzle count', function () {
    $user = User::factory()->create();
    Crossword::factory()->count(3)->create(['user_id' => $user->id, 'is_published' => false]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('draftCount'))->toBe(3);
});

test('dashboard shows solved puzzle count', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(2)->completed()->create(['user_id' => $user->id]);
    PuzzleAttempt::factory()->create(['user_id' => $user->id]); // in progress

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('solvedCount'))->toBe(2);
});

test('dashboard shows in-progress attempts', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create(['title' => 'Active Puzzle']);
    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => false,
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Active Puzzle');
});

test('dashboard limits in-progress attempts to 3', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(5)->create(['user_id' => $user->id, 'is_completed' => false]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('inProgressAttempts'))->toHaveCount(3);
});

test('dashboard shows recent draft puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->create(['user_id' => $user->id, 'title' => 'Draft Dash Puzzle', 'is_published' => false]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Draft Dash Puzzle');
});

test('dashboard limits recent drafts to 3', function () {
    $user = User::factory()->create();
    Crossword::factory()->count(5)->create(['user_id' => $user->id, 'is_published' => false]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('recentDrafts'))->toHaveCount(3);
});

test('dashboard shows community stats', function () {
    Crossword::factory()->count(4)->published()->create();
    PuzzleAttempt::factory()->count(2)->completed()->create();

    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('totalPublishedPuzzles'))->toBe(4)
        ->and($component->get('totalSolves'))->toBe(2);
});

test('dashboard shows newest puzzles from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Crossword::factory()->published()->create(['user_id' => $other->id, 'title' => 'Fresh Puzzle']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Fresh Puzzle');
});

test('dashboard newest excludes own puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->create(['user_id' => $user->id, 'title' => 'My Own Puzzle']);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('newestPuzzles'))->toHaveCount(0);
});

test('dashboard limits newest puzzles to 3', function () {
    $user = User::factory()->create();
    Crossword::factory()->count(5)->published()->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('newestPuzzles'))->toHaveCount(3);
});

test('dashboard shows trending puzzles based on recent likes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->published()->create(['user_id' => $other->id, 'title' => 'Hot Puzzle']);

    // Add recent likes
    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);
    CrosswordLike::create(['user_id' => User::factory()->create()->id, 'crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Hot Puzzle');
});

test('dashboard shows liked count for authenticated user', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('likedCount'))->toBe(1);
});

test('dashboard shows empty states when user has no activity', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard')
        ->assertSee('No puzzles in progress')
        ->assertSee('No drafts in progress');
});

test('dashboard shows following feed when user follows constructors', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Crossword::factory()->published()->create([
        'user_id' => $constructor->id,
        'title' => 'Followed Constructor Puzzle',
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('From People You Follow')
        ->assertSee('Followed Constructor Puzzle');
});

test('dashboard hides following feed when user follows nobody', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertDontSee('From People You Follow');
});

test('following feed only includes published puzzles', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Crossword::factory()->create([
        'user_id' => $constructor->id,
        'title' => 'Draft From Follow',
        'is_published' => false,
    ]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('followingPuzzles'))->toHaveCount(0);
});

test('following feed limits to 6 puzzles', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Crossword::factory()->count(8)->published()->create(['user_id' => $constructor->id]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('followingPuzzles'))->toHaveCount(6);
});

test('following feed shows puzzles from multiple followed constructors', function () {
    $user = User::factory()->create();
    $constructor1 = User::factory()->create();
    $constructor2 = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor1->id]);
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor2->id]);

    Crossword::factory()->published()->create(['user_id' => $constructor1->id, 'title' => 'Puzzle From First']);
    Crossword::factory()->published()->create(['user_id' => $constructor2->id, 'title' => 'Puzzle From Second']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Puzzle From First')
        ->assertSee('Puzzle From Second');
});

test('following count badge shows correct number', function () {
    $user = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => User::factory()->create()->id]);
    Follow::create(['follower_id' => $user->id, 'following_id' => User::factory()->create()->id]);
    Follow::create(['follower_id' => $user->id, 'following_id' => User::factory()->create()->id]);

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->get('followingCount'))->toBe(3);
});

test('following feed does not include unfollowed constructors puzzles', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $notFollowed = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $followed->id]);

    Crossword::factory()->published()->create(['user_id' => $followed->id, 'title' => 'Should See This']);
    Crossword::factory()->published()->create(['user_id' => $notFollowed->id, 'title' => 'Should Not See This']);

    $component = Livewire::actingAs($user)->test('pages::dashboard');
    $titles = $component->get('followingPuzzles')->pluck('title')->all();

    expect($titles)->toContain('Should See This')
        ->and($titles)->not->toContain('Should Not See This');
});

test('following feed shows empty state when followed constructors have no published puzzles', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('From People You Follow')
        ->assertSee('No new puzzles from people you follow yet.');
});
