<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

/**
 * The discovery component renders each puzzle as a child <livewire:puzzle-card>.
 * After a wire:model update the child's inner markup (title, stats) is not
 * re-rendered into the parent's HTML, so assertions on card content are
 * unreliable. The parent always emits the card's wire:key marker, however, so
 * we assert visibility via that marker instead.
 */
function cardKey(Crossword $crossword): string
{
    return 'wire:key="card-'.$crossword->id.'"';
}

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

test('discovery renders for guests when exclude filters are enabled', function () {
    Crossword::factory()->published()->create(['title' => 'Guest Visible Puzzle']);

    Livewire::test('puzzle-discovery', ['excludeOwn' => true, 'excludeAttempted' => true])
        ->assertOk()
        ->assertSeeHtml('wire:name="puzzle-discovery"');
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
    $ocean = Crossword::factory()->published()->for($creator)->create(['title' => 'Ocean Adventure']);
    $space = Crossword::factory()->published()->for($creator)->create(['title' => 'Space Journey']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Ocean')
        ->assertSeeHtml(cardKey($ocean))
        ->assertDontSeeHtml(cardKey($space));
});

test('discovery filters by search term on constructor name', function () {
    $user = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice Builder']);
    $bob = User::factory()->create(['name' => 'Bob Smith']);
    $alicePuzzle = Crossword::factory()->published()->for($alice)->create(['title' => 'Alice Puzzle']);
    $bobPuzzle = Crossword::factory()->published()->for($bob)->create(['title' => 'Bob Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Alice')
        ->assertSeeHtml(cardKey($alicePuzzle))
        ->assertDontSeeHtml(cardKey($bobPuzzle));
});

test('discovery search matches crossword author field', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create(['name' => 'Account Name']);
    $mystery = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Mystery Puzzle',
        'author' => 'Pen Name Author',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Pen Name')
        ->assertSeeHtml(cardKey($mystery));
});

test('discovery filters by small grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tiny = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Tiny Grid',
        'width' => 7,
        'height' => 7,
        'grid' => Crossword::emptyGrid(7, 7),
        'solution' => Crossword::emptySolution(7, 7),
    ]);
    $big = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Big Grid',
        'width' => 15,
        'height' => 15,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('gridSize', 'small')
        ->assertSeeHtml(cardKey($tiny))
        ->assertDontSeeHtml(cardKey($big));
});

test('discovery filters by medium grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $medium = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Medium Grid',
        'width' => 15,
        'height' => 15,
    ]);
    $small = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Small Grid',
        'width' => 7,
        'height' => 7,
        'grid' => Crossword::emptyGrid(7, 7),
        'solution' => Crossword::emptySolution(7, 7),
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('gridSize', 'medium')
        ->assertSeeHtml(cardKey($medium))
        ->assertDontSeeHtml(cardKey($small));
});

test('discovery filters by large grid size', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $sunday = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Sunday Puzzle',
        'width' => 21,
        'height' => 21,
        'grid' => Crossword::emptyGrid(21, 21),
        'solution' => Crossword::emptySolution(21, 21),
    ]);
    $regular = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Regular Puzzle',
        'width' => 15,
        'height' => 15,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('gridSize', 'large')
        ->assertSeeHtml(cardKey($sunday))
        ->assertDontSeeHtml(cardKey($regular));
});

test('discovery filters by standard puzzle type', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $standard = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Standard Puzzle',
        'puzzle_type' => 'standard',
    ]);

    $diamond = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Diamond Puzzle',
        'puzzle_type' => 'diamond',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('puzzleType', 'standard')
        ->assertSeeHtml(cardKey($standard))
        ->assertDontSeeHtml(cardKey($diamond));
});

test('discovery filters by diamond puzzle type', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $normal = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Normal Puzzle',
        'puzzle_type' => 'standard',
    ]);

    $diamond = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Diamond Shaped',
        'puzzle_type' => 'diamond',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('puzzleType', 'diamond')
        ->assertSeeHtml(cardKey($diamond))
        ->assertDontSeeHtml(cardKey($normal));
});

test('discovery filters by freestyle puzzle type', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $standard = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Standard Puzzle',
        'puzzle_type' => 'standard',
    ]);

    $freestyle = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Freestyle Puzzle',
        'puzzle_type' => 'freestyle',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('puzzleType', 'freestyle')
        ->assertSeeHtml(cardKey($freestyle))
        ->assertDontSeeHtml(cardKey($standard));
});

test('discovery filters by constructor user name', function () {
    $user = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice Constructor']);
    $bob = User::factory()->create(['name' => 'Bob Constructor']);
    $aliceWork = Crossword::factory()->published()->for($alice)->create(['title' => 'Alice Work']);
    $bobWork = Crossword::factory()->published()->for($bob)->create(['title' => 'Bob Work']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('constructor', 'Alice')
        ->assertSeeHtml(cardKey($aliceWork))
        ->assertDontSeeHtml(cardKey($bobWork));
});

test('discovery filters by crossword author field', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create(['name' => 'Account Name']);
    $penName = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Pen Name Puzzle',
        'author' => 'Pen Name',
    ]);
    $other = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Other Puzzle',
        'author' => 'Different Author',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('constructor', 'Pen')
        ->assertSeeHtml(cardKey($penName))
        ->assertDontSeeHtml(cardKey($other));
});

test('discovery filters by date range', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $recent = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Recent Puzzle',
        'created_at' => now(),
    ]);
    $old = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Old Puzzle',
        'created_at' => now()->subMonths(2),
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('dateRange', 'month')
        ->assertSeeHtml(cardKey($recent))
        ->assertDontSeeHtml(cardKey($old));
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
        ->assertSeeHtmlInOrder([cardKey($moreLiked), cardKey($lessLiked)]);
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
        ->set('minRating', '3')
        ->set('sortBy', 'oldest')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('gridSize', '')
        ->assertSet('puzzleType', '')
        ->assertSet('constructor', '')
        ->assertSet('dateRange', '')
        ->assertSet('minRating', '')
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

test('puzzle card toggle like creates and removes likes', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $component = Livewire::actingAs($user)
        ->test('puzzle-card', ['crossword' => $crossword]);

    $component->call('toggleLike');
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();

    $component->call('toggleLike');
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('puzzle card start solving redirects to solver', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-card', ['crossword' => $crossword])
        ->call('startSolving')
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('puzzle card start solving rejects unpublished puzzle', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-card', ['crossword' => $crossword])
        ->call('startSolving')
        ->assertForbidden();
});

test('discovery shows attempted puzzles when search is active', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Already Started Puzzle']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertDontSeeHtml(cardKey($crossword))
        ->set('search', 'Already')
        ->assertSeeHtml(cardKey($crossword));
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
        ->assertDontSeeHtml(cardKey($crossword))
        ->set('search', 'Some')
        ->set('constructor', 'Test')
        ->assertSeeHtml(cardKey($crossword));
});

test('discovery filters by difficulty label', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $easy = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Puzzle',
        'difficulty_label' => 'Easy',
        'difficulty_score' => 1.5,
    ]);
    $hard = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard Puzzle',
        'difficulty_label' => 'Hard',
        'difficulty_score' => 3.5,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', 'Easy')
        ->assertSeeHtml(cardKey($easy))
        ->assertDontSeeHtml(cardKey($hard));
});

test('discovery filters by each difficulty level', function (string $label) {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $match = Crossword::factory()->published()->for($creator)->create([
        'title' => "{$label} Puzzle",
        'difficulty_label' => $label,
    ]);
    $other = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Other Puzzle',
        'difficulty_label' => $label === 'Easy' ? 'Hard' : 'Easy',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', $label)
        ->assertSeeHtml(cardKey($match))
        ->assertDontSeeHtml(cardKey($other));
})->with(['Easy', 'Medium', 'Hard', 'Expert']);

test('discovery shows all puzzles when difficulty filter is empty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $easy = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy One',
        'difficulty_label' => 'Easy',
    ]);
    $hard = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard One',
        'difficulty_label' => 'Hard',
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('difficulty', '')
        ->assertSeeHtml(cardKey($easy))
        ->assertSeeHtml(cardKey($hard));
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

    $untagged = Crossword::factory()->published()->for($creator)->create(['title' => 'Untagged Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('tag', 'science')
        ->assertSeeHtml(cardKey($tagged))
        ->assertDontSeeHtml(cardKey($untagged));
});

test('discovery shows all puzzles when tag filter is empty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $tag = Tag::factory()->create(['name' => 'History', 'slug' => 'history']);

    $tagged = Crossword::factory()->published()->for($creator)->create(['title' => 'History Puzzle']);
    $tagged->tags()->attach($tag);

    $other = Crossword::factory()->published()->for($creator)->create(['title' => 'Other Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('tag', '')
        ->assertSeeHtml(cardKey($tagged))
        ->assertSeeHtml(cardKey($other));
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

// --- Solve Stats on Discovery Cards ---

test('discovery cards show solve count for completed attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Stats Puzzle']);

    PuzzleAttempt::factory()->completed()->count(3)->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertSee('3 solves');
});

test('discovery cards show singular solve label for one completion', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Single Solve']);

    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertSee('1 solve');
});

test('discovery cards show zero solves when no completions exist', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Unsolved Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertSee('0 solves');
});

test('discovery cards show average solve time for completed attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Timed Puzzle']);

    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 300,
    ]);
    PuzzleAttempt::factory()->completed()->create([
        'crossword_id' => $crossword->id,
        'solve_time_seconds' => 600,
    ]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertSee('avg 7:30');
});

test('discovery cards hide average time when no completed attempts exist', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'No Times Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertDontSee('avg ');
});

test('discovery sorts by most solved', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $lessSolved = Crossword::factory()->published()->for($creator)->create(['title' => 'Less Solved']);
    $moreSolved = Crossword::factory()->published()->for($creator)->create(['title' => 'More Solved']);

    PuzzleAttempt::factory()->completed()->count(5)->create(['crossword_id' => $moreSolved->id]);
    PuzzleAttempt::factory()->completed()->count(1)->create(['crossword_id' => $lessSolved->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->set('sortBy', 'most_solved')
        ->assertSeeHtmlInOrder([cardKey($moreSolved), cardKey($lessSolved)]);
});

// --- Rating & Play Count ---

test('discovery sorts by highest rated', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $lowRated = Crossword::factory()->published()->for($creator)->create(['title' => 'Low Rated']);
    $highRated = Crossword::factory()->published()->for($creator)->create(['title' => 'High Rated']);

    PuzzleComment::factory()->create(['crossword_id' => $lowRated->id, 'rating' => 2]);
    PuzzleComment::factory()->create(['crossword_id' => $highRated->id, 'rating' => 5]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('sortBy', 'highest_rated')
        ->assertSeeHtmlInOrder([cardKey($highRated), cardKey($lowRated)]);
});

test('discovery sorts by most played', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $lessPlayed = Crossword::factory()->published()->for($creator)->create(['title' => 'Less Played']);
    $morePlayed = Crossword::factory()->published()->for($creator)->create(['title' => 'More Played']);

    PuzzleAttempt::factory()->count(1)->create(['crossword_id' => $lessPlayed->id]);
    PuzzleAttempt::factory()->count(5)->create(['crossword_id' => $morePlayed->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('sortBy', 'most_played')
        ->assertSeeHtmlInOrder([cardKey($morePlayed), cardKey($lessPlayed)]);
});

test('discovery shows average rating stars on puzzle cards', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Rated Puzzle']);
    PuzzleComment::factory()->create(['crossword_id' => $puzzle->id, 'rating' => 4]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Rated Puzzle')
        ->assertSee('out of 5');
});

test('discovery shows play count on puzzle cards', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $puzzle = Crossword::factory()->published()->for($creator)->create(['title' => 'Popular Puzzle']);
    PuzzleAttempt::factory()->count(3)->create(['crossword_id' => $puzzle->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Popular Puzzle')
        ->assertSee('3 plays');
});

test('discovery hides rating stars when puzzle has no ratings', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create(['title' => 'Unrated Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Unrated Puzzle')
        ->assertDontSee('out of 5');
});

test('discovery hides play count when puzzle has no attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create(['title' => 'Fresh Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Fresh Puzzle')
        ->assertDontSee('plays');
});

// --- Completion Rate ---

test('discovery cards show completion rate percentage', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Rate Puzzle']);

    PuzzleAttempt::factory()->completed()->count(3)->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->count(2)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Rate Puzzle')
        ->assertSee('60%');
});

test('discovery cards show 100% completion rate when all attempts completed', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Perfect Puzzle']);

    PuzzleAttempt::factory()->completed()->count(4)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Perfect Puzzle')
        ->assertSee('100%');
});

test('discovery cards show 0% completion rate when no attempts completed', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Tough Puzzle']);

    PuzzleAttempt::factory()->count(3)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Tough Puzzle')
        ->assertSee('0%');
});

test('discovery cards hide completion rate when puzzle has no attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Untouched Puzzle']);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Untouched Puzzle')
        ->assertDontSee('of solvers completed this puzzle');
});

test('discovery cards show completion rate tooltip', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Tooltip Puzzle']);

    PuzzleAttempt::factory()->completed()->count(1)->create(['crossword_id' => $crossword->id]);
    PuzzleAttempt::factory()->count(1)->create(['crossword_id' => $crossword->id]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Tooltip Puzzle')
        ->assertSeeHtml('50% of solvers completed this puzzle');
});

test('discovery clear filters resets new sort options', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('sortBy', 'highest_rated')
        ->call('clearFilters')
        ->assertSet('sortBy', 'newest');

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('sortBy', 'most_played')
        ->call('clearFilters')
        ->assertSet('sortBy', 'newest');
});

// --- Minimum Rating Filter ---

test('discovery filters by minimum rating', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $highRated = Crossword::factory()->published()->for($creator)->create(['title' => 'High Rated Puzzle']);
    $lowRated = Crossword::factory()->published()->for($creator)->create(['title' => 'Low Rated Puzzle']);
    $unrated = Crossword::factory()->published()->for($creator)->create(['title' => 'Unrated Puzzle']);

    PuzzleComment::factory()->create(['crossword_id' => $highRated->id, 'rating' => 5]);
    PuzzleComment::factory()->create(['crossword_id' => $highRated->id, 'rating' => 4]);
    PuzzleComment::factory()->create(['crossword_id' => $lowRated->id, 'rating' => 2]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('minRating', '4')
        ->assertSeeHtml(cardKey($highRated))
        ->assertDontSeeHtml(cardKey($lowRated))
        ->assertDontSeeHtml(cardKey($unrated));
});

test('discovery shows all puzzles when minimum rating is empty', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $rated = Crossword::factory()->published()->for($creator)->create(['title' => 'Rated Puzzle']);
    $unrated = Crossword::factory()->published()->for($creator)->create(['title' => 'Unrated Puzzle']);

    PuzzleComment::factory()->create(['crossword_id' => $rated->id, 'rating' => 3]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('minRating', '')
        ->assertSeeHtml(cardKey($rated))
        ->assertSeeHtml(cardKey($unrated));
});

test('discovery minimum rating filter excludes unrated puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $noRatings = Crossword::factory()->published()->for($creator)->create(['title' => 'No Ratings Puzzle']);
    $rated = Crossword::factory()->published()->for($creator)->create(['title' => 'Has Rating']);
    PuzzleComment::factory()->create(['crossword_id' => $rated->id, 'rating' => 3]);

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('minRating', '1')
        ->assertSeeHtml(cardKey($rated))
        ->assertDontSeeHtml(cardKey($noRatings));
});

test('discovery clear filters resets minimum rating', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('minRating', '4')
        ->call('clearFilters')
        ->assertSet('minRating', '');
});

test('discovery shows filter indicator when minimum rating is active', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('puzzle-discovery')
        ->assertDontSee('Clear')
        ->set('minRating', '3')
        ->assertSee('Clear');
});
