<?php

use App\Models\Contest;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('authenticated users can visit the solve page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.solving'))
        ->assertOk();
});

test('solve page shows solved puzzle count', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(2)->completed()->create(['user_id' => $user->id]);
    PuzzleAttempt::factory()->create(['user_id' => $user->id]); // in progress

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('solvedCount'))->toBe(2);
});

test('solve page shows liked count for authenticated user', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('likedCount'))->toBe(1);
});

test('solve page shows community stats', function () {
    Crossword::factory()->count(4)->published()->create();
    PuzzleAttempt::factory()->count(2)->completed()->create();

    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('totalPublishedPuzzles'))->toBe(4)
        ->and($component->get('totalSolves'))->toBe(2);
});

test('solve page shows newest puzzles from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Crossword::factory()->published()->create(['user_id' => $other->id, 'title' => 'Fresh Puzzle']);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Fresh Puzzle');
});

test('solve page newest excludes own puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->create(['user_id' => $user->id, 'title' => 'My Own Puzzle']);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('newestPuzzles'))->toHaveCount(0);
});

test('solve page limits newest puzzles to 3', function () {
    $user = User::factory()->create();
    Crossword::factory()->count(5)->published()->create();

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('newestPuzzles'))->toHaveCount(3);
});

test('solve page shows trending puzzles based on recent likes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $crossword = Crossword::factory()->published()->create(['user_id' => $other->id, 'title' => 'Hot Puzzle']);

    // Add recent likes
    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);
    CrosswordLike::create(['user_id' => User::factory()->create()->id, 'crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Hot Puzzle');
});

test('solve page shows following feed when user follows constructors', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Crossword::factory()->published()->create([
        'user_id' => $constructor->id,
        'title' => 'Followed Constructor Puzzle',
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('From People You Follow')
        ->assertSee('Followed Constructor Puzzle');
});

test('solve page hides following feed when user follows nobody', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
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

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('followingPuzzles'))->toHaveCount(0);
});

test('following feed limits to 6 puzzles', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Crossword::factory()->count(8)->published()->create(['user_id' => $constructor->id]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

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
        ->test('pages::crosswords.solving')
        ->assertSee('Puzzle From First')
        ->assertSee('Puzzle From Second');
});

test('following count badge shows correct number', function () {
    $user = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => User::factory()->create()->id]);
    Follow::create(['follower_id' => $user->id, 'following_id' => User::factory()->create()->id]);
    Follow::create(['follower_id' => $user->id, 'following_id' => User::factory()->create()->id]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('followingCount'))->toBe(3);
});

test('following feed does not include unfollowed constructors puzzles', function () {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $notFollowed = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $followed->id]);

    Crossword::factory()->published()->create(['user_id' => $followed->id, 'title' => 'Should See This']);
    Crossword::factory()->published()->create(['user_id' => $notFollowed->id, 'title' => 'Should Not See This']);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');
    $titles = $component->get('followingPuzzles')->pluck('title')->all();

    expect($titles)->toContain('Should See This')
        ->and($titles)->not->toContain('Should Not See This');
});

test('following feed shows empty state when followed constructors have no published puzzles', function () {
    $user = User::factory()->create();
    $constructor = User::factory()->create();
    Follow::create(['follower_id' => $user->id, 'following_id' => $constructor->id]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('From People You Follow')
        ->assertSee('No new puzzles from people you follow yet.');
});

test('solve page shows active contests', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->create(['title' => 'Spring Crossword Challenge']);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Contests')
        ->assertSee('Spring Crossword Challenge')
        ->assertSee('Active');
});

test('solve page shows upcoming contests', function () {
    $user = User::factory()->create();
    Contest::factory()->upcoming()->create(['title' => 'Summer Puzzle Fest']);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Contests')
        ->assertSee('Summer Puzzle Fest')
        ->assertSee('Upcoming');
});

test('solve page hides contests section when none are active or upcoming', function () {
    $user = User::factory()->create();
    Contest::factory()->ended()->create(['title' => 'Old Contest']);
    Contest::factory()->draft()->create(['title' => 'Draft Contest']);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertDontSee('Old Contest')
        ->assertDontSee('Draft Contest');
});

test('solve page limits active contests to 3', function () {
    $user = User::factory()->create();
    Contest::factory()->count(5)->active()->create();

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('activeContests'))->toHaveCount(3);
});

test('solve page limits upcoming contests to 3', function () {
    $user = User::factory()->create();
    Contest::factory()->count(5)->upcoming()->create();

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('upcomingContests'))->toHaveCount(3);
});

test('solve page shows featured badge on featured contests', function () {
    $user = User::factory()->create();
    Contest::factory()->active()->featured()->create(['title' => 'Featured Contest']);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Featured Contest')
        ->assertSee('Featured');
});

test('solve page shows contest participant and puzzle counts', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    $crosswords = Crossword::factory()->count(3)->published()->create();
    $contest->crosswords()->attach($crosswords->pluck('id')->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]]));

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('activeContests')->first()->crosswords_count)->toBe(3);
});

// --- Solving Streak ---

test('solve page shows solving streak card when user has a streak', function () {
    $user = User::factory()->create([
        'current_streak' => 5,
        'longest_streak' => 12,
        'last_solve_date' => today()->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Solving Streak')
        ->assertSee('5 days in a row!');
});

test('streak card shows active badge when solved today', function () {
    $user = User::factory()->create([
        'current_streak' => 3,
        'longest_streak' => 3,
        'last_solve_date' => today()->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Active');
});

test('streak card shows active badge when solved yesterday', function () {
    $user = User::factory()->create([
        'current_streak' => 7,
        'longest_streak' => 7,
        'last_solve_date' => today()->subDay()->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Active');
});

test('streak card shows inactive when streak is broken', function () {
    $user = User::factory()->create([
        'current_streak' => 3,
        'longest_streak' => 10,
        'last_solve_date' => today()->subDays(3)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Solving Streak')
        ->assertSee('Inactive')
        ->assertSee('Solve a puzzle today to start a new streak.');
});

test('streak card shows solve now button when inactive', function () {
    $user = User::factory()->create([
        'current_streak' => 2,
        'longest_streak' => 5,
        'last_solve_date' => today()->subDays(5)->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('Solve Now');
});

test('streak card does not show solve now button when active', function () {
    $user = User::factory()->create([
        'current_streak' => 4,
        'longest_streak' => 4,
        'last_solve_date' => today()->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertDontSee('Solve Now');
});

test('solve page hides streak card when user has no streak history', function () {
    $user = User::factory()->create([
        'current_streak' => 0,
        'longest_streak' => 0,
        'last_solve_date' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertDontSee('Solving Streak');
});

test('streak computed properties return correct values', function () {
    $user = User::factory()->create([
        'current_streak' => 8,
        'longest_streak' => 15,
        'last_solve_date' => today()->toDateString(),
    ]);

    $component = Livewire::actingAs($user)->test('pages::crosswords.solving');

    expect($component->get('currentStreak'))->toBe(8)
        ->and($component->get('longestStreak'))->toBe(15)
        ->and($component->get('streakIsActive'))->toBeTrue();
});

test('streak shows singular day for streak of one', function () {
    $user = User::factory()->create([
        'current_streak' => 1,
        'longest_streak' => 1,
        'last_solve_date' => today()->toDateString(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSee('1 day in a row!');
});
