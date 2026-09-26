<?php

use App\Models\Contest;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('the solve page introduces the discovery list with a rule and a Discover Puzzles title', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSeeInOrder(['data-test="discover-puzzles-section"', 'data-test="discover-puzzles-rule"', 'Discover Puzzles', 'wire:name="puzzle-discovery"'], false)
        ->assertSee('class="border-hairline -mx-6 border-t lg:-mx-8"', false);
});

test('authenticated users can visit the solve page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.solving'))
        ->assertOk();
});

test('the solve page header carries the headline solve stats band', function () {
    $user = User::factory()->create(['current_streak' => 3, 'longest_streak' => 9]);
    PuzzleAttempt::factory()->count(2)->completed()->create(['user_id' => $user->id, 'solve_time_seconds' => 90]);
    PuzzleAttempt::factory()->create(['user_id' => $user->id]); // in progress

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSeeInOrder(['data-page-header-footer', 'data-test="solver-stat"', 'Puzzles Solved', 'Current Streak', 'Best Streak', 'Average Time', 'Fastest Solve'], false)
        ->assertDontSee('Faster Than Avg')
        ->assertDontSee('data-test="solver-stat"><!--', false);

    Livewire::actingAs($user)
        ->test('solver-stats')
        ->assertSet('totalSolved', 2)
        ->assertSet('currentStreak', 3)
        ->assertSet('longestStreak', 9)
        ->assertSet('averageTime', 90)
        ->assertSee('1:30')
        ->assertSeeInOrder(['3 days', '9 days']);
});

test('the solve stats band shows a dash before any timed solve', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('solver-stats')
        ->assertSet('totalSolved', 0)
        ->assertSet('averageTime', null)
        ->assertSee('—');
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

test('the attempts grid collapses to one row with a toggle, like the Build page results', function () {
    $user = User::factory()->create();
    PuzzleAttempt::factory()->count(3)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solving')
        ->assertSeeInOrder(['x-data="collapsibleGrid"', 'data-test="attempt-results-grid"', 'wire:key="attempt-', 'data-test="toggle-all-attempts-button"'], false)
        ->assertSee('Show all puzzles')
        ->assertSee('Show fewer');
});
