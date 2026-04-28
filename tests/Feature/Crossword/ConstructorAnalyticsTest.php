<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

function makeAnalyticsProUser(): User
{
    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    return $user;
}

test('analytics page is accessible for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Constructor Analytics');
});

test('analytics page shows empty state without published puzzles', function () {
    $user = makeAnalyticsProUser();

    $this->actingAs($user)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Publish puzzles to see analytics');
});

test('analytics page shows puzzle performance data', function () {
    $constructor = makeAnalyticsProUser();
    $solver = User::factory()->create();

    $crossword = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Analytics Test Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->for($solver)->for($crossword)->completed()->create([
        'progress' => [['A', 'B'], ['C', 'D']],
        'solve_time_seconds' => 120,
    ]);

    $this->actingAs($constructor)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Analytics Test Puzzle')
        ->assertSee('2:00');
});

test('analytics counts solves and completions across all published puzzles', function () {
    $constructor = makeAnalyticsProUser();
    $solver1 = User::factory()->create();
    $solver2 = User::factory()->create();

    $puzzle1 = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $puzzle2 = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->for($solver1)->for($puzzle1)->completed()->create();
    PuzzleAttempt::factory()->for($solver2)->for($puzzle1)->create();
    PuzzleAttempt::factory()->for($solver1)->for($puzzle2)->completed()->create();

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('totalSolves'))->toBe(3)
        ->and($component->get('totalCompletions'))->toBe(2);
});

test('analytics link appears on my puzzles page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Analytics');
});

test('non-pro users see upgrade prompt', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.analytics')
        ->assertSee('Upgrade to Pro')
        ->assertSee('Get detailed analytics')
        ->assertDontSee('Puzzle Performance');
});

test('pro users see full analytics dashboard', function () {
    $constructor = makeAnalyticsProUser();
    Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    Livewire::actingAs($constructor)
        ->test('pages::crosswords.analytics')
        ->assertDontSee('Upgrade to Pro')
        ->assertSee('Puzzle Performance');
});

test('total solves counts all attempts on published puzzles', function () {
    $constructor = makeAnalyticsProUser();

    $published = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $draft = Crossword::factory()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->count(3)->for($published)->create();
    PuzzleAttempt::factory()->count(2)->for($draft)->create();

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('totalSolves'))->toBe(3);
});

test('total completions only counts completed attempts', function () {
    $constructor = makeAnalyticsProUser();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->count(2)->completed()->for($puzzle)->create();
    PuzzleAttempt::factory()->count(3)->for($puzzle)->create();

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('totalCompletions'))->toBe(2);
});

test('overall average solve time computes correctly', function () {
    $constructor = makeAnalyticsProUser();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->completed()->for($puzzle)->create(['solve_time_seconds' => 100]);
    PuzzleAttempt::factory()->completed()->for($puzzle)->create(['solve_time_seconds' => 200]);
    PuzzleAttempt::factory()->for($puzzle)->create(['solve_time_seconds' => null]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('overallAvgSolveTime'))->toBe(150);
});

test('overall average solve time is null when no completed attempts', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('overallAvgSolveTime'))->toBeNull();
});

test('total likes counts likes on published puzzles only', function () {
    $constructor = makeAnalyticsProUser();
    $liker = User::factory()->create();

    $published = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $draft = Crossword::factory()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    CrosswordLike::create(['user_id' => $liker->id, 'crossword_id' => $published->id]);
    CrosswordLike::create(['user_id' => $liker->id, 'crossword_id' => $draft->id]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('totalLikes'))->toBe(1);
});

test('published puzzles table includes attempt and like counts', function () {
    $constructor = makeAnalyticsProUser();
    $solver = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Counted Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->count(5)->for($puzzle)->create();
    PuzzleAttempt::factory()->count(3)->completed()->for($puzzle)->create();
    CrosswordLike::create(['user_id' => $solver->id, 'crossword_id' => $puzzle->id]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');
    $puzzles = $component->get('publishedPuzzles');

    expect($puzzles)->toHaveCount(1)
        ->and($puzzles->first()->attempts_count)->toBe(8)
        ->and($puzzles->first()->completed_attempts_count)->toBe(3)
        ->and($puzzles->first()->likes_count)->toBe(1);
});

test('sorting by title toggles direction', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Alpha',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Zulu',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    $component->call('sortBy', 'title');
    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->title)->toBe('Alpha');

    $component->call('sortBy', 'title');
    $puzzles = $component->get('publishedPuzzles');
    expect($puzzles->first()->title)->toBe('Zulu');
});

test('sorting by attempts count orders correctly', function () {
    $constructor = makeAnalyticsProUser();

    $less = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Less Popular',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $more = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'More Popular',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->count(2)->for($less)->create();
    PuzzleAttempt::factory()->count(5)->for($more)->create();

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    $component->call('sortBy', 'attempts_count');
    expect($component->get('publishedPuzzles')->first()->title)->toBe('Less Popular');

    $component->call('sortBy', 'attempts_count');
    expect($component->get('publishedPuzzles')->first()->title)->toBe('More Popular');
});

test('sorting by likes count orders correctly', function () {
    $constructor = makeAnalyticsProUser();
    $liker1 = User::factory()->create();
    $liker2 = User::factory()->create();

    $lessLiked = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Less Liked',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $moreLiked = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'More Liked',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    CrosswordLike::create(['user_id' => $liker1->id, 'crossword_id' => $lessLiked->id]);
    CrosswordLike::create(['user_id' => $liker1->id, 'crossword_id' => $moreLiked->id]);
    CrosswordLike::create(['user_id' => $liker2->id, 'crossword_id' => $moreLiked->id]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    $component->call('sortBy', 'likes_count');
    expect($component->get('publishedPuzzles')->first()->title)->toBe('Less Liked');

    $component->call('sortBy', 'likes_count');
    expect($component->get('publishedPuzzles')->first()->title)->toBe('More Liked');
});

test('default sort is by latest created', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Older',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'created_at' => now()->subDays(5),
    ]);
    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Newer',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'created_at' => now(),
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');
    $puzzles = $component->get('publishedPuzzles');

    expect($puzzles->first()->title)->toBe('Newer');
});

test('draft puzzles are excluded from performance table', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Published One',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    Crossword::factory()->for($constructor)->create([
        'title' => 'Draft One',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'is_published' => false,
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('publishedPuzzles'))->toHaveCount(1)
        ->and($component->get('publishedPuzzles')->first()->title)->toBe('Published One');
});

test('other users puzzles are not shown', function () {
    $constructor = makeAnalyticsProUser();
    $other = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'title' => 'My Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    Crossword::factory()->published()->for($other)->create([
        'title' => 'Their Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('publishedPuzzles'))->toHaveCount(1)
        ->and($component->get('publishedPuzzles')->first()->title)->toBe('My Puzzle');
});

test('format time renders minutes and seconds', function () {
    $constructor = makeAnalyticsProUser();

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->call('formatTime', 90)->get(''))
        // Use invocation via object to test the method directly
        ->and(invade($component->instance())->formatTime(0))->toBe('—')
        ->and(invade($component->instance())->formatTime(null))->toBe('—')
        ->and(invade($component->instance())->formatTime(90))->toBe('1:30')
        ->and(invade($component->instance())->formatTime(3661))->toBe('1:01:01')
        ->and(invade($component->instance())->formatTime(59))->toBe('0:59');
});

test('completion rate calculates correctly', function () {
    $constructor = makeAnalyticsProUser();

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');
    $instance = invade($component->instance());

    expect($instance->completionRate(0, 0))->toBe('0%')
        ->and($instance->completionRate(10, 5))->toBe('50%')
        ->and($instance->completionRate(3, 3))->toBe('100%')
        ->and($instance->completionRate(3, 1))->toBe('33%');
});

test('cell difficulty returns data for puzzles with completed attempts', function () {
    $constructor = makeAnalyticsProUser();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Difficulty Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    PuzzleAttempt::factory()->completed()->for($puzzle)->create([
        'progress' => [['A', 'B'], ['C', 'D']],
        'solve_time_seconds' => 120,
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');
    $difficulty = $component->get('cellDifficulty');

    expect($difficulty)->toHaveCount(1)
        ->and($difficulty[0]['title'])->toBe('Difficulty Puzzle')
        ->and($difficulty[0]['width'])->toBe(2)
        ->and($difficulty[0]['height'])->toBe(2)
        ->and($difficulty[0]['attempt_count'])->toBe(1)
        ->and($difficulty[0]['avg_time'])->toBe(120);
});

test('cell difficulty is empty when no puzzles have completed attempts', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'solution' => [['A', 'B'], ['C', 'D']],
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('cellDifficulty'))->toHaveCount(0);
});

test('cell difficulty limits to 3 puzzles', function () {
    $constructor = makeAnalyticsProUser();

    for ($i = 0; $i < 5; $i++) {
        $puzzle = Crossword::factory()->published()->for($constructor)->create([
            'title' => "Puzzle {$i}",
            'width' => 2,
            'height' => 2,
            'grid' => [[1, 2], [3, 0]],
            'solution' => [['A', 'B'], ['C', 'D']],
        ]);

        PuzzleAttempt::factory()->completed()->for($puzzle)->create([
            'solve_time_seconds' => 100 + $i * 10,
        ]);
    }

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('cellDifficulty'))->toHaveCount(3);
});

test('guests cannot access analytics page', function () {
    $this->get(route('crosswords.analytics'))
        ->assertRedirect(route('login'));
});

test('average solve time displayed in performance table', function () {
    $constructor = makeAnalyticsProUser();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Timed Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleAttempt::factory()->for($puzzle)->create([
        'solve_time_seconds' => 300,
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');
    $puzzles = $component->get('publishedPuzzles');

    expect((int) round($puzzles->first()->avg_solve_time))->toBe(300);
});

test('sorting by a new field resets direction to ascending', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->count(2)->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    $component->call('sortBy', 'title');
    $component->call('sortBy', 'title');
    expect($component->get('sortDirection'))->toBe('desc');

    $component->call('sortBy', 'likes_count');
    expect($component->get('sortDirection'))->toBe('asc')
        ->and($component->get('sortField'))->toBe('likes_count');
});

test('overall average rating computes from published puzzle comments', function () {
    $constructor = makeAnalyticsProUser();
    $solver1 = User::factory()->create();
    $solver2 = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleComment::create(['user_id' => $solver1->id, 'crossword_id' => $puzzle->id, 'body' => 'Great!', 'rating' => 5]);
    PuzzleComment::create(['user_id' => $solver2->id, 'crossword_id' => $puzzle->id, 'body' => 'OK', 'rating' => 3]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('overallAvgRating'))->toBe(4.0);
});

test('overall average rating excludes draft puzzle comments', function () {
    $constructor = makeAnalyticsProUser();
    $solver = User::factory()->create();

    $published = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $draft = Crossword::factory()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'is_published' => false,
    ]);

    PuzzleComment::create(['user_id' => $solver->id, 'crossword_id' => $published->id, 'body' => 'Nice', 'rating' => 4]);
    PuzzleComment::create(['user_id' => User::factory()->create()->id, 'crossword_id' => $draft->id, 'body' => 'Meh', 'rating' => 1]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('overallAvgRating'))->toBe(4.0);
});

test('overall average rating is null when no reviews exist', function () {
    $constructor = makeAnalyticsProUser();

    Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('overallAvgRating'))->toBeNull();
});

test('total reviews counts only comments with ratings on published puzzles', function () {
    $constructor = makeAnalyticsProUser();
    $solver1 = User::factory()->create();
    $solver2 = User::factory()->create();

    $published = Crossword::factory()->published()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $draft = Crossword::factory()->for($constructor)->create([
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
        'is_published' => false,
    ]);

    PuzzleComment::create(['user_id' => $solver1->id, 'crossword_id' => $published->id, 'body' => 'Good', 'rating' => 4]);
    PuzzleComment::create(['user_id' => $solver2->id, 'crossword_id' => $published->id, 'body' => 'Nice', 'rating' => 5]);
    PuzzleComment::create(['user_id' => $solver1->id, 'crossword_id' => $draft->id, 'body' => 'Draft', 'rating' => 2]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    expect($component->get('totalReviews'))->toBe(2);
});

test('published puzzles table includes review count and average rating', function () {
    $constructor = makeAnalyticsProUser();
    $solver1 = User::factory()->create();
    $solver2 = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Rated Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleComment::create(['user_id' => $solver1->id, 'crossword_id' => $puzzle->id, 'body' => 'Loved it', 'rating' => 5]);
    PuzzleComment::create(['user_id' => $solver2->id, 'crossword_id' => $puzzle->id, 'body' => 'Fun', 'rating' => 3]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');
    $puzzles = $component->get('publishedPuzzles');

    expect($puzzles)->toHaveCount(1)
        ->and($puzzles->first()->reviews_count)->toBe(2)
        ->and(round((float) $puzzles->first()->avg_rating, 1))->toBe(4.0);
});

test('sorting by average rating orders correctly', function () {
    $constructor = makeAnalyticsProUser();
    $solver = User::factory()->create();

    $lowRated = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Low Rated',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);
    $highRated = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'High Rated',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleComment::create(['user_id' => $solver->id, 'crossword_id' => $lowRated->id, 'body' => 'Meh', 'rating' => 2]);
    PuzzleComment::create(['user_id' => User::factory()->create()->id, 'crossword_id' => $highRated->id, 'body' => 'Amazing', 'rating' => 5]);

    $component = Livewire::actingAs($constructor)->test('pages::crosswords.analytics');

    $component->call('sortBy', 'avg_rating');
    expect($component->get('publishedPuzzles')->first()->title)->toBe('Low Rated');

    $component->call('sortBy', 'avg_rating');
    expect($component->get('publishedPuzzles')->first()->title)->toBe('High Rated');
});

test('rating displays on analytics page for pro users', function () {
    $constructor = makeAnalyticsProUser();
    $solver = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($constructor)->create([
        'title' => 'Reviewed Puzzle',
        'width' => 2,
        'height' => 2,
        'grid' => [[1, 2], [3, 0]],
    ]);

    PuzzleComment::create(['user_id' => $solver->id, 'crossword_id' => $puzzle->id, 'body' => 'Excellent', 'rating' => 5]);

    $this->actingAs($constructor)
        ->get(route('crosswords.analytics'))
        ->assertOk()
        ->assertSee('Avg Rating')
        ->assertSee('1 review');
});
