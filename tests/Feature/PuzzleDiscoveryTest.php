<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\PuzzleAttempt;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

test('discovery shows only published puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Visible Puzzle']);
    Crossword::factory()->for($creator)->create(['title' => 'Hidden Draft']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Visible Puzzle')
        ->assertDontSee('Hidden Draft');
});

test('discovery shows own published puzzles by default', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->create(['title' => 'My Published Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('My Published Puzzle');
});

test('discovery excludes own puzzles when explicitly configured', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->create(['title' => 'My Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeOwn' => true])
        ->assertDontSee('My Puzzle');
});

test('discovery excludes already attempted puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Attempted One']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertDontSee('Attempted One');
});

test('discovery filters by search term on title', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Ocean Adventure']);
    Crossword::factory()->published()->for($creator)->create(['title' => 'Space Journey']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Ocean')
        ->assertSee('Ocean Adventure')
        ->assertDontSee('Space Journey');
});

test('discovery filters by search term on constructor name', function () {
    $user = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice Builder']);
    $bob = User::factory()->create(['name' => 'Bob Smith']);
    Crossword::factory()->published()->for($alice)->create(['title' => 'Alice Puzzle']);
    Crossword::factory()->published()->for($bob)->create(['title' => 'Bob Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Alice')
        ->assertSee('Alice Puzzle')
        ->assertDontSee('Bob Puzzle');
});

test('discovery search matches crossword author field', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create(['name' => 'Account Name']);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Mystery Puzzle',
        'author' => 'Pen Name Author',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Pen Name')
        ->assertSee('Mystery Puzzle');
});

test('discovery filters by small grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Tiny Grid',
        'width' => 7,
        'height' => 7,
        'grid' => Crossword::emptyGrid(7, 7),
        'solution' => Crossword::emptySolution(7, 7),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Big Grid',
        'width' => 15,
        'height' => 15,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('gridSize', 'small')
        ->assertSee('Tiny Grid')
        ->assertDontSee('Big Grid');
});

test('discovery filters by medium grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Medium Grid',
        'width' => 15,
        'height' => 15,
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Small Grid',
        'width' => 7,
        'height' => 7,
        'grid' => Crossword::emptyGrid(7, 7),
        'solution' => Crossword::emptySolution(7, 7),
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('gridSize', 'medium')
        ->assertSee('Medium Grid')
        ->assertDontSee('Small Grid');
});

test('discovery filters by large grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Sunday Puzzle',
        'width' => 21,
        'height' => 21,
        'grid' => Crossword::emptyGrid(21, 21),
        'solution' => Crossword::emptySolution(21, 21),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Regular Puzzle',
        'width' => 15,
        'height' => 15,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('gridSize', 'large')
        ->assertSee('Sunday Puzzle')
        ->assertDontSee('Regular Puzzle');
});

test('discovery filters by standard puzzle type', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Standard Puzzle',
        'puzzle_type' => 'standard',
    ]);

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Diamond Puzzle',
        'puzzle_type' => 'diamond',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('puzzleType', 'standard')
        ->assertSee('Standard Puzzle')
        ->assertDontSee('Diamond Puzzle');
});

test('discovery filters by diamond puzzle type', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Normal Puzzle',
        'puzzle_type' => 'standard',
    ]);

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Diamond Shaped',
        'puzzle_type' => 'diamond',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('puzzleType', 'diamond')
        ->assertSee('Diamond Shaped')
        ->assertDontSee('Normal Puzzle');
});

test('discovery filters by freestyle puzzle type', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Standard Puzzle',
        'puzzle_type' => 'standard',
    ]);

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Freestyle Puzzle',
        'puzzle_type' => 'freestyle',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('puzzleType', 'freestyle')
        ->assertSee('Freestyle Puzzle')
        ->assertDontSee('Standard Puzzle');
});

test('discovery filters by constructor user name', function () {
    $user = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice Constructor']);
    $bob = User::factory()->create(['name' => 'Bob Constructor']);
    Crossword::factory()->published()->for($alice)->create(['title' => 'Alice Work']);
    Crossword::factory()->published()->for($bob)->create(['title' => 'Bob Work']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('constructor', 'Alice')
        ->assertSee('Alice Work')
        ->assertDontSee('Bob Work');
});

test('discovery filters by crossword author field', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create(['name' => 'Account Name']);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Pen Name Puzzle',
        'author' => 'Pen Name',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Other Puzzle',
        'author' => 'Different Author',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('constructor', 'Pen')
        ->assertSee('Pen Name Puzzle')
        ->assertDontSee('Other Puzzle');
});

test('discovery filters by date range', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Recent Puzzle',
        'created_at' => now(),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Old Puzzle',
        'created_at' => now()->subMonths(2),
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('dateRange', 'month')
        ->assertSee('Recent Puzzle')
        ->assertDontSee('Old Puzzle');
});

test('discovery sorts by most liked', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $lessLiked = Crossword::factory()->published()->for($creator)->create(['title' => 'Less Liked']);
    $moreLiked = Crossword::factory()->published()->for($creator)->create(['title' => 'More Liked']);

    CrosswordLike::factory()->count(5)->create(['crossword_id' => $moreLiked->id]);
    CrosswordLike::factory()->count(1)->create(['crossword_id' => $lessLiked->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('sortBy', 'most_liked')
        ->assertSeeInOrder(['More Liked', 'Less Liked']);
});

test('discovery clear filters resets all filters', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'test')
        ->set('gridSize', 'small')
        ->set('puzzleType', 'standard')
        ->set('constructor', 'Alice')
        ->set('dateRange', 'week')
        ->set('sortBy', 'oldest')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('gridSize', '')
        ->assertSet('puzzleType', '')
        ->assertSet('constructor', '')
        ->assertSet('dateRange', '')
        ->assertSet('sortBy', 'newest');
});

test('discovery respects limit parameter', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $puzzles = Crossword::factory()->published()->for($creator)->count(5)->create();

    $component = Livewire::actingAs($user)
        ->test('puzzle-discovery', ['limit' => 3, 'excludeAttempted' => true]);

    $component->assertSee($puzzles[0]->title)
        ->assertDontSee($puzzles[4]->title);
});

test('discovery paginates when limit is zero', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $oldest = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Oldest Puzzle Here',
        'created_at' => now()->subDays(30),
    ]);
    Crossword::factory()->published()->for($creator)->count(19)->create();

    $component = Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true]);

    $component->assertDontSee('Oldest Puzzle Here');
});

test('discovery toggle like creates and removes likes', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $component = Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true]);

    $component->call('toggleLike', $crossword->id);
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();

    $component->call('toggleLike', $crossword->id);
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('discovery start solving redirects to solver', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->call('startSolving', $crossword->id)
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('discovery start solving rejects unpublished puzzle', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->call('startSolving', $crossword->id)
        ->assertForbidden();
});

test('discovery shows attempted puzzles when search is active', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Already Started Puzzle']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertDontSee('Already Started Puzzle')
        ->set('search', 'Already')
        ->assertSee('Already Started Puzzle');
});

test('discovery shows own attempted puzzles when constructor filter is set', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $crossword = Crossword::factory()->published()->for($user)->create([
        'title' => 'Some Untitled Puzzle',
        'author' => 'Test User',
    ]);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true, 'excludeOwn' => true])
        ->assertDontSee('Some Untitled Puzzle')
        ->set('search', 'Some')
        ->set('constructor', 'Test')
        ->assertSee('Some Untitled Puzzle');
});

test('discovery filters by difficulty label', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Puzzle',
        'difficulty_label' => 'Easy',
        'difficulty_score' => 1.5,
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard Puzzle',
        'difficulty_label' => 'Hard',
        'difficulty_score' => 3.5,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', 'Easy')
        ->assertSee('Easy Puzzle')
        ->assertDontSee('Hard Puzzle');
});

test('discovery filters by each difficulty level', function (string $label) {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => "{$label} Puzzle",
        'difficulty_label' => $label,
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Other Puzzle',
        'difficulty_label' => $label === 'Easy' ? 'Hard' : 'Easy',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', $label)
        ->assertSee("{$label} Puzzle")
        ->assertDontSee('Other Puzzle');
})->with(['Easy', 'Medium', 'Hard', 'Expert']);

test('discovery shows all puzzles when difficulty filter is empty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy One',
        'difficulty_label' => 'Easy',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard One',
        'difficulty_label' => 'Hard',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', '')
        ->assertSee('Easy One')
        ->assertSee('Hard One');
});

test('discovery clear filters resets difficulty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', 'Hard')
        ->call('clearFilters')
        ->assertSet('difficulty', '');
});

test('discovery shows filter indicator when difficulty is active', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'difficulty_label' => 'Hard',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertDontSee('Clear')
        ->set('difficulty', 'Hard')
        ->assertSee('Clear');
});

test('discovery shows filter indicator when filters are active', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    $component = Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertDontSee('Clear');

    $component->set('search', 'test')
        ->assertSee('Clear');
});

// --- Tag Filtering ---

test('discovery filters by tag slug', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Science', 'slug' => 'science']);

    $tagged = Crossword::factory()->published()->for($creator)->create(['title' => 'Science Puzzle']);
    $tagged->tags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Untagged Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('tag', 'science')
        ->assertSee('Science Puzzle')
        ->assertDontSee('Untagged Puzzle');
});

test('discovery shows all puzzles when tag filter is empty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'History', 'slug' => 'history']);

    $tagged = Crossword::factory()->published()->for($creator)->create(['title' => 'History Puzzle']);
    $tagged->tags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Other Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('tag', '')
        ->assertSee('History Puzzle')
        ->assertSee('Other Puzzle');
});

test('discovery clear filters resets tag', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('tag', 'science')
        ->call('clearFilters')
        ->assertSet('tag', '');
});

test('discovery shows tag filter indicator when tag is active', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();
    Tag::factory()->create(['name' => 'Music', 'slug' => 'music']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertDontSee('Clear')
        ->set('tag', 'music')
        ->assertSee('Clear');
});

test('discovery displays tags on puzzle cards', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Pop Culture']);

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Tagged Card Puzzle']);
    $puzzle->tags()->attach($tag);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Pop Culture');
});

// --- Blocked Tag Exclusion in Discovery ---

test('discovery hides puzzles with blocked tags', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Cryptic']);

    $blockedPuzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Blocked Discovery Puzzle']);
    $blockedPuzzle->tags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Visible Discovery Puzzle']);

    $user->blockedTags()->attach($tag);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertDontSee('Blocked Discovery Puzzle')
        ->assertSee('Visible Discovery Puzzle');
});

test('discovery hides puzzles if any tag is blocked', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $blockedTag = Tag::factory()->create(['name' => 'Blocked Tag']);
    $safeTag = Tag::factory()->create(['name' => 'Safe Tag']);

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Multi Tag Discovery']);
    $puzzle->tags()->attach([$blockedTag->id, $safeTag->id]);

    $user->blockedTags()->attach($blockedTag);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertDontSee('Multi Tag Discovery');
});

test('discovery shows untagged puzzles when user has blocked tags', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'Blocked']);

    $user->blockedTags()->attach($tag);

    Crossword::factory()->published()->for($creator)->create(['title' => 'No Tags Discovery']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('No Tags Discovery');
});

test('discovery shows puzzles with non-blocked tags', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $blockedTag = Tag::factory()->create(['name' => 'Blocked']);
    $safeTag = Tag::factory()->create(['name' => 'Safe']);

    $user->blockedTags()->attach($blockedTag);

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Safe Discovery Puzzle']);
    $puzzle->tags()->attach($safeTag);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Safe Discovery Puzzle');
});
