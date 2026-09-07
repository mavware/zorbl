<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('leaderboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the leaderboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('leaderboard'))
        ->assertOk();
});

test('leaderboard defaults to solvers tab', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard')
        ->assertSee('Top Solvers');
});

test('top solvers ranks users by completed puzzles', function () {
    $topSolver = User::factory()->create(['name' => 'Top Solver']);
    $casualSolver = User::factory()->create(['name' => 'Casual Solver']);

    PuzzleAttempt::factory()->count(5)->completed()->create(['user_id' => $topSolver->id]);
    PuzzleAttempt::factory()->count(2)->completed()->create(['user_id' => $casualSolver->id]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard');

    $solvers = $component->get('topSolvers');

    expect($solvers->first()->name)->toBe('Top Solver')
        ->and($solvers->first()->completed_count)->toBe(5)
        ->and($solvers->last()->name)->toBe('Casual Solver')
        ->and($solvers->last()->completed_count)->toBe(2);
});

test('top solvers excludes users with no completed puzzles', function () {
    $solver = User::factory()->create(['name' => 'Active Solver']);
    $inactive = User::factory()->create(['name' => 'Inactive User']);

    PuzzleAttempt::factory()->completed()->create(['user_id' => $solver->id]);
    PuzzleAttempt::factory()->create(['user_id' => $inactive->id, 'is_completed' => false]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard');

    $names = $component->get('topSolvers')->pluck('name')->all();

    expect($names)->toContain('Active Solver')
        ->and($names)->not->toContain('Inactive User');
});

test('speed demons ranks users by average solve time', function () {
    $fastSolver = User::factory()->create(['name' => 'Fast Solver']);
    $slowSolver = User::factory()->create(['name' => 'Slow Solver']);

    PuzzleAttempt::factory()->count(5)->completed()->create([
        'user_id' => $fastSolver->id,
        'solve_time_seconds' => 120,
    ]);
    PuzzleAttempt::factory()->count(5)->completed()->create([
        'user_id' => $slowSolver->id,
        'solve_time_seconds' => 600,
    ]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'speed']);

    $demons = $component->get('speedDemons');

    expect($demons->first()->name)->toBe('Fast Solver')
        ->and($demons->last()->name)->toBe('Slow Solver');
});

test('speed demons requires minimum 5 solves', function () {
    $eligible = User::factory()->create(['name' => 'Eligible']);
    $tooFew = User::factory()->create(['name' => 'Too Few Solves']);

    PuzzleAttempt::factory()->count(5)->completed()->create([
        'user_id' => $eligible->id,
        'solve_time_seconds' => 200,
    ]);
    PuzzleAttempt::factory()->count(4)->completed()->create([
        'user_id' => $tooFew->id,
        'solve_time_seconds' => 100,
    ]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'speed']);

    $names = $component->get('speedDemons')->pluck('name')->all();

    expect($names)->toContain('Eligible')
        ->and($names)->not->toContain('Too Few Solves');
});

test('top constructors ranks by total solves on published puzzles', function () {
    $popularConstructor = User::factory()->create(['name' => 'Popular Constructor']);
    $newConstructor = User::factory()->create(['name' => 'New Constructor']);

    $popularPuzzle = Crossword::factory()->published()->create(['user_id' => $popularConstructor->id]);
    $newPuzzle = Crossword::factory()->published()->create(['user_id' => $newConstructor->id]);

    PuzzleAttempt::factory()->count(10)->completed()->create(['crossword_id' => $popularPuzzle->id]);
    PuzzleAttempt::factory()->count(2)->completed()->create(['crossword_id' => $newPuzzle->id]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'constructors']);

    $constructors = $component->get('topConstructors');

    expect($constructors->first()->name)->toBe('Popular Constructor')
        ->and($constructors->first()->total_solves)->toBe(10);
});

test('top constructors excludes unpublished puzzles', function () {
    $constructor = User::factory()->create(['name' => 'Draft Only']);
    Crossword::factory()->create(['user_id' => $constructor->id, 'is_published' => false]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'constructors']);

    $names = $component->get('topConstructors')->pluck('name')->all();

    expect($names)->not->toContain('Draft Only');
});

test('streak leaders ranks by longest streak', function () {
    User::factory()->create(['name' => 'Streak King', 'longest_streak' => 30, 'current_streak' => 5]);
    User::factory()->create(['name' => 'Consistent Solver', 'longest_streak' => 15, 'current_streak' => 15]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'streaks']);

    $leaders = $component->get('streakLeaders');

    expect($leaders->first()->name)->toBe('Streak King')
        ->and($leaders->first()->longest_streak)->toBe(30);
});

test('streak leaders excludes users with no streak', function () {
    User::factory()->create(['name' => 'Has Streak', 'longest_streak' => 5]);
    User::factory()->create(['name' => 'No Streak', 'longest_streak' => 0]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'streaks']);

    $names = $component->get('streakLeaders')->pluck('name')->all();

    expect($names)->toContain('Has Streak')
        ->and($names)->not->toContain('No Streak');
});

test('top rated ranks constructors by average puzzle rating', function () {
    $highRated = User::factory()->create(['name' => 'High Rated']);
    $lowRated = User::factory()->create(['name' => 'Low Rated']);

    $highPuzzle = Crossword::factory()->published()->create(['user_id' => $highRated->id]);
    $lowPuzzle = Crossword::factory()->published()->create(['user_id' => $lowRated->id]);

    PuzzleComment::factory()->count(3)->create(['crossword_id' => $highPuzzle->id, 'rating' => 5]);
    PuzzleComment::factory()->count(3)->create(['crossword_id' => $lowPuzzle->id, 'rating' => 2]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'rated']);

    $rated = $component->get('topRated');

    expect($rated->first()->name)->toBe('High Rated')
        ->and((float) $rated->first()->avg_rating)->toBe(5.0)
        ->and($rated->last()->name)->toBe('Low Rated');
});

test('top rated requires minimum 3 reviews', function () {
    $eligible = User::factory()->create(['name' => 'Eligible']);
    $tooFew = User::factory()->create(['name' => 'Too Few Reviews']);

    $eligiblePuzzle = Crossword::factory()->published()->create(['user_id' => $eligible->id]);
    $tooFewPuzzle = Crossword::factory()->published()->create(['user_id' => $tooFew->id]);

    PuzzleComment::factory()->count(3)->create(['crossword_id' => $eligiblePuzzle->id, 'rating' => 4]);
    PuzzleComment::factory()->count(2)->create(['crossword_id' => $tooFewPuzzle->id, 'rating' => 5]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'rated']);

    $names = $component->get('topRated')->pluck('name')->all();

    expect($names)->toContain('Eligible')
        ->and($names)->not->toContain('Too Few Reviews');
});

test('top rated excludes unpublished puzzles', function () {
    $constructor = User::factory()->create(['name' => 'Draft Only']);
    $puzzle = Crossword::factory()->create(['user_id' => $constructor->id, 'is_published' => false]);
    PuzzleComment::factory()->count(5)->create(['crossword_id' => $puzzle->id, 'rating' => 5]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'rated']);

    $names = $component->get('topRated')->pluck('name')->all();

    expect($names)->not->toContain('Draft Only');
});

test('top rated excludes reviews without a rating', function () {
    $constructor = User::factory()->create(['name' => 'No Ratings']);
    $puzzle = Crossword::factory()->published()->create(['user_id' => $constructor->id]);
    PuzzleComment::factory()->count(5)->create(['crossword_id' => $puzzle->id, 'rating' => null]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'rated']);

    $names = $component->get('topRated')->pluck('name')->all();

    expect($names)->not->toContain('No Ratings');
});

test('tab can be switched via url parameter', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard', ['tab' => 'constructors'])
        ->assertSee('Top Constructors');
});

test('leaderboard highlights current user row', function () {
    $user = User::factory()->create(['name' => 'Current User']);
    PuzzleAttempt::factory()->count(3)->completed()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::leaderboard')
        ->assertSee('Current User');
});

test('leaderboard limits results to 50 per category', function () {
    User::factory()->count(55)->create()->each(function ($user) {
        PuzzleAttempt::factory()->completed()->create(['user_id' => $user->id]);
    });

    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard');

    expect($component->get('topSolvers'))->toHaveCount(50);
});

test('leaderboard shows empty state when no data exists', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard')
        ->assertSee('Not enough data to show rankings yet');
});

test('format time displays correctly', function () {
    $component = Livewire::actingAs(User::factory()->create())
        ->test('pages::leaderboard');

    expect($component->call('formatTime', 90)->get('tab'))->not->toBeNull();

    $instance = $component->instance();

    expect($instance->formatTime(90))->toBe('1:30')
        ->and($instance->formatTime(3661))->toBe('1:01:01')
        ->and($instance->formatTime(null))->toBe('—');
});

test('leaderboard tabs survive a round-trip through the database cache store', function () {
    // The database store serializes cached values and only unserializes
    // classes allowlisted in cache.serializable_classes, so caching Eloquent
    // models here would blow up on the second (cache-hit) render.
    config(['cache.default' => 'database']);

    $solver = User::factory()->create([
        'name' => 'Cached Solver',
        'longest_streak' => 3,
        'current_streak' => 2,
    ]);
    PuzzleAttempt::factory()->count(5)->completed()->create(['user_id' => $solver->id]);
    $puzzle = Crossword::factory()->published()->create(['user_id' => $solver->id]);
    PuzzleComment::factory()->count(3)->create(['crossword_id' => $puzzle->id, 'rating' => 4]);

    $viewer = User::factory()->create();

    foreach (['solvers', 'speed', 'constructors', 'streaks', 'rated'] as $tab) {
        // First render warms the cache; the second must unserialize the stored payload.
        Livewire::actingAs($viewer)->test('pages::leaderboard', ['tab' => $tab]);

        Livewire::actingAs($viewer)
            ->test('pages::leaderboard', ['tab' => $tab])
            ->assertSee('Cached Solver');
    }
});
