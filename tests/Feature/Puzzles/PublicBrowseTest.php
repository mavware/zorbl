<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\DailyPuzzle;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/**
 * Extract the class attribute of the puzzle-card div for the given crossword id.
 * Searches forward from the wire:key marker since `class` follows `wire:key`.
 */
function cardClasses(string $html, int $crosswordId): string
{
    $marker = "puzzle-card-{$crosswordId}";
    $markerPos = strpos($html, $marker);

    if ($markerPos === false) {
        return '';
    }

    $classStart = strpos($html, 'class="', $markerPos);

    if ($classStart === false) {
        return '';
    }

    $classEnd = strpos($html, '"', $classStart + 7);

    return substr($html, $classStart + 7, $classEnd - $classStart - 7);
}

/**
 * Extract the inner HTML slice for the puzzle-card div of the given crossword id,
 * up to the next puzzle-card marker or end of document.
 */
function cardSlice(string $html, int $crosswordId): string
{
    $marker = "puzzle-card-{$crosswordId}";
    $start = strpos($html, $marker);

    if ($start === false) {
        return '';
    }

    $nextMarker = strpos($html, 'puzzle-card-', $start + strlen($marker));

    return $nextMarker === false
        ? substr($html, $start)
        : substr($html, $start, $nextMarker - $start);
}

test('browse page loads without authentication', function () {
    Crossword::factory()->published()->create(['title' => 'Public Puzzle']);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Browse Puzzles')
        ->assertSee('Public Puzzle');
});

test('browse page shows only published puzzles', function () {
    Crossword::factory()->published()->create(['title' => 'Visible Puzzle']);
    Crossword::factory()->create(['title' => 'Draft Puzzle', 'is_published' => false]);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Visible Puzzle')
        ->assertDontSee('Draft Puzzle');
});

test('browse page shows puzzle metadata', function () {
    $crossword = Crossword::factory()->published()->create([
        'title' => 'Metadata Test',
        'width' => 15,
        'height' => 15,
    ]);

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Metadata Test')
        ->assertSee('15&times;15', false);
});

test('browse page shows try this puzzle button for guests', function () {
    Crossword::factory()->published()->create();

    $this->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Try This Puzzle');
});

test('browse page shows start solving button for authenticated users', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->create();

    $this->actingAs($user)
        ->get(route('puzzles.index'))
        ->assertOk()
        ->assertSee('Start Solving');
});

// --- Search ---

test('search filters puzzles by title', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Ocean Adventure']);
    Crossword::factory()->published()->for($creator)->create(['title' => 'Space Journey']);

    Livewire::test('pages::puzzles.index')
        ->set('search', 'Ocean')
        ->assertSee('Ocean Adventure')
        ->assertDontSee('Space Journey');
});

test('search filters puzzles by author field', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Author Match',
        'author' => 'Jane Doe',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'No Match',
        'author' => 'John Smith',
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('search', 'Jane')
        ->assertSee('Author Match')
        ->assertDontSee('No Match');
});

test('search filters puzzles by constructor user name', function () {
    $alice = User::factory()->create(['name' => 'Alice Builder']);
    $bob = User::factory()->create(['name' => 'Bob Smith']);
    Crossword::factory()->published()->for($alice)->create(['title' => 'Alice Puzzle']);
    Crossword::factory()->published()->for($bob)->create(['title' => 'Bob Puzzle']);

    Livewire::test('pages::puzzles.index')
        ->set('search', 'Alice')
        ->assertSee('Alice Puzzle')
        ->assertDontSee('Bob Puzzle');
});

// --- Difficulty Filter ---

test('difficulty filter shows only matching puzzles', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Puzzle',
        'difficulty_label' => 'Easy',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard Puzzle',
        'difficulty_label' => 'Hard',
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('difficulty', 'Easy')
        ->assertSee('Easy Puzzle')
        ->assertDontSee('Hard Puzzle');
});

test('difficulty filter shows all puzzles when empty', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy One',
        'difficulty_label' => 'Easy',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard One',
        'difficulty_label' => 'Hard',
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('difficulty', '')
        ->assertSee('Easy One')
        ->assertSee('Hard One');
});

// --- Grid Size Filter ---

test('grid size filter shows small puzzles', function () {
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

    Livewire::test('pages::puzzles.index')
        ->set('gridSize', 'small')
        ->assertSee('Tiny Grid')
        ->assertDontSee('Big Grid');
});

test('grid size filter shows medium puzzles', function () {
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

    Livewire::test('pages::puzzles.index')
        ->set('gridSize', 'medium')
        ->assertSee('Medium Grid')
        ->assertDontSee('Small Grid');
});

test('grid size filter shows large puzzles', function () {
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

    Livewire::test('pages::puzzles.index')
        ->set('gridSize', 'large')
        ->assertSee('Sunday Puzzle')
        ->assertDontSee('Regular Puzzle');
});

// --- Puzzle Type Filter ---

test('puzzle type filter shows standard puzzles', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Standard Puzzle',
        'styles' => [],
    ]);

    $shapedGrid = Crossword::emptyGrid(15, 15);
    $shapedGrid[0][0] = null;
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Shaped Puzzle',
        'grid' => $shapedGrid,
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('puzzleType', 'standard')
        ->assertSee('Standard Puzzle')
        ->assertDontSee('Shaped Puzzle');
});

test('puzzle type filter shows shaped puzzles', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Normal Puzzle',
        'styles' => [],
    ]);

    $shapedGrid = Crossword::emptyGrid(15, 15);
    $shapedGrid[0][0] = null;
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Diamond Shaped',
        'grid' => $shapedGrid,
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('puzzleType', 'shaped')
        ->assertSee('Diamond Shaped')
        ->assertDontSee('Normal Puzzle');
});

test('puzzle type filter shows barred puzzles', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Plain Puzzle',
        'styles' => [],
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Barred Puzzle',
        'styles' => [['bars' => ['bottom']]],
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('puzzleType', 'barred')
        ->assertSee('Barred Puzzle')
        ->assertDontSee('Plain Puzzle');
});

// --- Constructor Filter ---

test('constructor filter matches user name', function () {
    $alice = User::factory()->create(['name' => 'Alice Constructor']);
    $bob = User::factory()->create(['name' => 'Bob Constructor']);
    Crossword::factory()->published()->for($alice)->create(['title' => 'Alice Work']);
    Crossword::factory()->published()->for($bob)->create(['title' => 'Bob Work']);

    Livewire::test('pages::puzzles.index')
        ->set('constructor', 'Alice')
        ->assertSee('Alice Work')
        ->assertDontSee('Bob Work');
});

test('constructor filter matches author field', function () {
    $creator = User::factory()->create(['name' => 'Account Name']);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Pen Name Puzzle',
        'author' => 'Pen Name',
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Other Puzzle',
        'author' => 'Different Author',
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('constructor', 'Pen')
        ->assertSee('Pen Name Puzzle')
        ->assertDontSee('Other Puzzle');
});

// --- Date Range Filter ---

test('date range filter shows puzzles from today', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Today Puzzle',
        'created_at' => now(),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Yesterday Puzzle',
        'created_at' => now()->subDays(2),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('dateRange', 'today')
        ->assertSee('Today Puzzle')
        ->assertDontSee('Yesterday Puzzle');
});

test('date range filter shows puzzles from this week', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Recent Puzzle',
        'created_at' => now()->subDays(3),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Old Puzzle',
        'created_at' => now()->subMonths(2),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('dateRange', 'week')
        ->assertSee('Recent Puzzle')
        ->assertDontSee('Old Puzzle');
});

test('date range filter shows puzzles from this month', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'This Month Puzzle',
        'created_at' => now()->subDays(10),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Older Puzzle',
        'created_at' => now()->subMonths(3),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('dateRange', 'month')
        ->assertSee('This Month Puzzle')
        ->assertDontSee('Older Puzzle');
});

test('date range filter shows puzzles from this year', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'This Year Puzzle',
        'created_at' => now()->subMonths(3),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Ancient Puzzle',
        'created_at' => now()->subYears(2),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('dateRange', 'year')
        ->assertSee('This Year Puzzle')
        ->assertDontSee('Ancient Puzzle');
});

// --- Sort Options ---

test('sort by newest shows most recent first', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Older Puzzle',
        'created_at' => now()->subDays(5),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Newer Puzzle',
        'created_at' => now(),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'newest')
        ->assertSeeInOrder(['Newer Puzzle', 'Older Puzzle']);
});

test('sort by oldest shows oldest first', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Older Puzzle',
        'created_at' => now()->subDays(5),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Newer Puzzle',
        'created_at' => now(),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'oldest')
        ->assertSeeInOrder(['Older Puzzle', 'Newer Puzzle']);
});

test('sort by most liked orders by like count', function () {
    $creator = User::factory()->create();
    $lessLiked = Crossword::factory()->published()->for($creator)->create(['title' => 'Less Liked']);
    $moreLiked = Crossword::factory()->published()->for($creator)->create(['title' => 'More Liked']);

    CrosswordLike::factory()->count(5)->create(['crossword_id' => $moreLiked->id]);
    CrosswordLike::factory()->count(1)->create(['crossword_id' => $lessLiked->id]);

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'most_liked')
        ->assertSeeInOrder(['More Liked', 'Less Liked']);
});

test('sort by largest shows biggest grids first', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Small Grid',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
        'solution' => Crossword::emptySolution(5, 5),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Large Grid',
        'width' => 21,
        'height' => 21,
        'grid' => Crossword::emptyGrid(21, 21),
        'solution' => Crossword::emptySolution(21, 21),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'largest')
        ->assertSeeInOrder(['Large Grid', 'Small Grid']);
});

test('sort by smallest shows smallest grids first', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Small Grid',
        'width' => 5,
        'height' => 5,
        'grid' => Crossword::emptyGrid(5, 5),
        'solution' => Crossword::emptySolution(5, 5),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Large Grid',
        'width' => 21,
        'height' => 21,
        'grid' => Crossword::emptyGrid(21, 21),
        'solution' => Crossword::emptySolution(21, 21),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'smallest')
        ->assertSeeInOrder(['Small Grid', 'Large Grid']);
});

// --- Like Toggling ---

test('authenticated user can toggle like on a puzzle', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $component = Livewire::actingAs($user)->test('pages::puzzles.index');

    $component->call('toggleLike', $crossword->id);
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();

    $component->call('toggleLike', $crossword->id);
    expect(CrosswordLike::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeFalse();
});

test('guest toggling like redirects to login', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::test('pages::puzzles.index')
        ->call('toggleLike', $crossword->id)
        ->assertRedirect(route('login'));
});

test('liked puzzles are reflected in likedIds', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    CrosswordLike::create(['user_id' => $user->id, 'crossword_id' => $crossword->id]);

    $component = Livewire::actingAs($user)->test('pages::puzzles.index');

    expect($component->get('likedIds'))->toHaveKey($crossword->id);
});

test('guests have empty likedIds', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    $component = Livewire::test('pages::puzzles.index');

    expect($component->get('likedIds'))->toBeEmpty();
});

// --- Start Solving ---

test('authenticated user can start solving a published puzzle', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('startSolving', $crossword->id)
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('start solving rejects unpublished puzzle', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('pages::puzzles.index')
        ->call('startSolving', $crossword->id)
        ->assertNotFound();
});

// --- Clear Filters ---

test('clear filters resets all filter state', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::test('pages::puzzles.index')
        ->set('search', 'test')
        ->set('gridSize', 'small')
        ->set('puzzleType', 'standard')
        ->set('constructor', 'Alice')
        ->set('dateRange', 'week')
        ->set('difficulty', 'Easy')
        ->set('minRating', '4')
        ->set('sortBy', 'oldest')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('gridSize', '')
        ->assertSet('puzzleType', '')
        ->assertSet('constructor', '')
        ->assertSet('dateRange', '')
        ->assertSet('difficulty', '')
        ->assertSet('minRating', '')
        ->assertSet('sortBy', 'newest');
});

// --- hasActiveFilters ---

test('hasActiveFilters returns false with defaults', function () {
    Livewire::test('pages::puzzles.index')
        ->assertSet('search', '')
        ->assertSet('sortBy', 'newest');
});

test('hasActiveFilters returns true when search is set', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::test('pages::puzzles.index')
        ->set('search', 'test')
        ->assertSee('Clear All');
});

test('hasActiveFilters returns true when sort is changed', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'oldest')
        ->assertSee('Clear All');
});

// --- Puzzle Type Label ---

test('puzzle type label returns Standard for regular grids', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'styles' => [],
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Standard');
});

test('puzzle type label returns Shaped for grids with null cells', function () {
    $shapedGrid = Crossword::emptyGrid(15, 15);
    $shapedGrid[0][0] = null;
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'grid' => $shapedGrid,
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Shaped');
});

test('puzzle type label returns Barred for grids with bar styles', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'styles' => [['bars' => ['bottom']]],
    ]);

    expect($crossword->puzzleTypeLabel())->toBe('Barred');
});

// --- Combined Filters ---

test('multiple filters combine correctly', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Small Fresh',
        'difficulty_label' => 'Easy',
        'width' => 7,
        'height' => 7,
        'grid' => Crossword::emptyGrid(7, 7),
        'solution' => Crossword::emptySolution(7, 7),
        'created_at' => now(),
    ]);
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard Large Old',
        'difficulty_label' => 'Hard',
        'width' => 21,
        'height' => 21,
        'grid' => Crossword::emptyGrid(21, 21),
        'solution' => Crossword::emptySolution(21, 21),
        'created_at' => now()->subYears(2),
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('difficulty', 'Easy')
        ->set('gridSize', 'small')
        ->assertSee('Easy Small Fresh')
        ->assertDontSee('Hard Large Old');
});

// --- Empty State ---

test('empty state is shown when no puzzles match filters', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Existing Puzzle',
        'difficulty_label' => 'Easy',
    ]);

    Livewire::test('pages::puzzles.index')
        ->set('difficulty', 'Expert')
        ->assertSee('No puzzles found')
        ->assertSee('Try adjusting your filters');
});

test('empty state without filters shows no published puzzles message', function () {
    Livewire::test('pages::puzzles.index')
        ->assertSee('No puzzles found')
        ->assertSee('No published puzzles available');
});

// --- Difficulty Badge Display ---

test('difficulty badges are shown on puzzle cards', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Crossword',
        'difficulty_label' => 'Easy',
    ]);

    Livewire::test('pages::puzzles.index')
        ->assertSee('Easy Crossword')
        ->assertSee('Easy');
});

// --- Minimum Rating Filter ---

test('minimum rating filter shows only puzzles meeting threshold', function () {
    $creator = User::factory()->create();

    $highRated = Crossword::factory()->published()->for($creator)->create(['title' => 'Five Star Puzzle']);
    $lowRated = Crossword::factory()->published()->for($creator)->create(['title' => 'Two Star Puzzle']);

    PuzzleComment::factory()->create(['crossword_id' => $highRated->id, 'rating' => 5]);
    PuzzleComment::factory()->create(['crossword_id' => $lowRated->id, 'rating' => 2]);

    Livewire::test('pages::puzzles.index')
        ->set('minRating', '4')
        ->assertSee('Five Star Puzzle')
        ->assertDontSee('Two Star Puzzle');
});

test('minimum rating filter excludes unrated puzzles', function () {
    $creator = User::factory()->create();

    Crossword::factory()->published()->for($creator)->create(['title' => 'Unrated Puzzle']);
    $rated = Crossword::factory()->published()->for($creator)->create(['title' => 'Rated Puzzle']);
    PuzzleComment::factory()->create(['crossword_id' => $rated->id, 'rating' => 3]);

    Livewire::test('pages::puzzles.index')
        ->set('minRating', '3')
        ->assertSee('Rated Puzzle')
        ->assertDontSee('Unrated Puzzle');
});

test('minimum rating filter shows all puzzles when empty', function () {
    $creator = User::factory()->create();

    $rated = Crossword::factory()->published()->for($creator)->create(['title' => 'Has Rating']);
    Crossword::factory()->published()->for($creator)->create(['title' => 'No Rating']);
    PuzzleComment::factory()->create(['crossword_id' => $rated->id, 'rating' => 5]);

    Livewire::test('pages::puzzles.index')
        ->set('minRating', '')
        ->assertSee('Has Rating')
        ->assertSee('No Rating');
});

test('clear filters resets minimum rating', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::test('pages::puzzles.index')
        ->set('minRating', '4')
        ->call('clearFilters')
        ->assertSet('minRating', '');
});

test('hasActiveFilters returns true when minimum rating is set', function () {
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create();

    Livewire::test('pages::puzzles.index')
        ->set('minRating', '3')
        ->assertSee('Clear All');
});

// --- Solved Indicator ---

test('solved puzzle card shows the green check indicator', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Solved Marker Puzzle',
    ]);

    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $crossword->id,
    ]);

    $html = Livewire::actingAs($user)->test('pages::puzzles.index')->html();
    $slice = cardSlice($html, $crossword->id);

    expect($slice)->toContain('title="Solved"')
        ->and($slice)->toContain('text-emerald-500');
});

test('unsolved puzzle card does not show solved indicator', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Unsolved Marker Puzzle',
    ]);

    $html = Livewire::actingAs($user)->test('pages::puzzles.index')->html();
    $slice = cardSlice($html, $crossword->id);

    expect($slice)->not->toContain('title="Solved"');
});

test('unauthenticated user sees no solved indicators', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Public Card',
    ]);

    // Another user solved the puzzle — guests should never see solved indicators.
    PuzzleAttempt::factory()->completed()->create(['crossword_id' => $crossword->id]);

    $html = Livewire::test('pages::puzzles.index')->html();
    $slice = cardSlice($html, $crossword->id);

    expect($slice)->not->toContain('title="Solved"');
});

test('solved daily puzzle uses corner check, not inline Solved badge', function () {
    Cache::flush();
    $user = User::factory()->create();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Solved Daily Puzzle',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $daily->id,
    ]);

    $html = Livewire::actingAs($user)->test('pages::puzzles.index')->html();
    $slice = cardSlice($html, $daily->id);

    // Corner check indicator should be present on the card slice.
    expect($slice)->toContain('title="Solved"');

    // The old inline "Solved" badge (a flux:badge) should be gone from the card.
    // The badge rendered an element containing the text "Solved" without the
    // title attribute — confirm we don't see a stray "Solved" outside the
    // title attribute by counting title="Solved" occurrences (should be 1).
    expect(substr_count($slice, 'title="Solved"'))->toBe(1);
});

// --- Card Click Navigation ---

test('puzzle card outer div has wire:click to start solving', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Clickable Puzzle',
    ]);

    $html = Livewire::test('pages::puzzles.index')->html();

    // Find the opening tag of the card's outer div (starts at wire:key marker
    // and ends at the next `>`). The tag should contain the wire:click attribute.
    $markerPos = strpos($html, "wire:key=\"puzzle-card-{$crossword->id}\"");
    expect($markerPos)->not->toBeFalse();

    $tagEnd = strpos($html, '>', $markerPos);
    $openingTag = substr($html, $markerPos, $tagEnd - $markerPos);

    expect($openingTag)->toContain("wire:click=\"startSolving({$crossword->id})\"");
});

// --- Puzzle of the Day Pinning ---

test('daily puzzle is pinned as first card in grid at default settings', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Featured Puzzle',
        'created_at' => now()->subDays(10),
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    $newer = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Newer Regular Puzzle',
        'created_at' => now(),
    ]);

    Livewire::test('pages::puzzles.index')
        ->assertSeeHtmlInOrder([
            "puzzle-card-{$daily->id}",
            "puzzle-card-{$newer->id}",
        ]);
});

test('daily puzzle is not pinned when search filter is active', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Featured Puzzle',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Searchable Puzzle']);

    Livewire::test('pages::puzzles.index')
        ->set('search', 'Searchable')
        ->assertDontSeeHtml("puzzle-card-{$daily->id}")
        ->assertSee('Searchable Puzzle');
});

test('daily puzzle is not pinned when sort is changed from default', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Featured Puzzle',
        'created_at' => now()->subDays(10),
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    $newer = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Newer Regular Puzzle',
        'created_at' => now(),
    ]);

    // With sortBy=oldest, daily (older) sorts first naturally, then newer.
    // Without pinning, the order is purely by created_at ASC.
    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'oldest')
        ->assertSeeHtmlInOrder([
            "puzzle-card-{$daily->id}",
            "puzzle-card-{$newer->id}",
        ]);

    // With sortBy=most_liked and the newer puzzle having a like, newer should
    // come first — proving pinning does not apply when sort is non-default.
    CrosswordLike::factory()->create(['crossword_id' => $newer->id]);

    Livewire::test('pages::puzzles.index')
        ->set('sortBy', 'most_liked')
        ->assertSeeHtmlInOrder([
            "puzzle-card-{$newer->id}",
            "puzzle-card-{$daily->id}",
        ]);
});

test('daily puzzle is not duplicated in grid when pinned', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Only Daily Puzzle',
        'created_at' => now(),
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    // The daily puzzle would naturally appear in the grid by created_at order.
    // When pinned, it should be excluded from the regular query to avoid
    // appearing twice as a grid card.
    $component = Livewire::test('pages::puzzles.index');

    $html = $component->html();
    $occurrences = substr_count($html, "puzzle-card-{$daily->id}");

    expect($occurrences)->toBe(1);
});

test('daily puzzle card has amber highlight when shown via active filter', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Filterable Puzzle',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Other Puzzle']);

    // Search filters out 'Other Puzzle', leaving only the daily — but since
    // a filter is active, the daily is not "pinned"; it lands in the grid via
    // the natural query. The highlight should still be applied to its card.
    $html = Livewire::test('pages::puzzles.index')
        ->set('search', 'Filterable')
        ->html();

    expect(cardClasses($html, $daily->id))
        ->toContain('bg-amber-50')
        ->toContain('border-amber-200');
});

test('non-daily puzzle card does not have amber highlight', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Featured Puzzle',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    $regular = Crossword::factory()->published()->for($creator)->create(['title' => 'Regular Puzzle']);

    $html = Livewire::test('pages::puzzles.index')->html();

    expect(cardClasses($html, $regular->id))->not->toContain('bg-amber-50');
});

test('daily puzzle card includes Puzzle of the Day header in grid', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Header Test',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Other Header Test']);

    $html = Livewire::test('pages::puzzles.index')->html();

    // The banner shows "Puzzle of the Day" once. With the pinned daily card
    // also rendering the header, the phrase should appear at least twice.
    $occurrences = substr_count($html, 'Puzzle of the Day');
    expect($occurrences)->toBeGreaterThanOrEqual(2);
});

test('daily puzzle card shows Puzzle of the Day header when surfaced via filter', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Filtered Header',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    Crossword::factory()->published()->for($creator)->create(['title' => 'Excluded Result']);

    $html = Livewire::test('pages::puzzles.index')
        ->set('search', 'Filtered')
        ->html();

    // Banner header + grid card header = at least 2 occurrences when the
    // daily puzzle surfaces via a filter.
    $occurrences = substr_count($html, 'Puzzle of the Day');
    expect($occurrences)->toBeGreaterThanOrEqual(2);
});

test('non-daily puzzle card does not include Puzzle of the Day header', function () {
    Cache::flush();
    $creator = User::factory()->create();

    // Set a daily puzzle so the banner appears, but isolate a different card
    // and ensure its rendered markup does not contain the header phrase.
    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Daily Marker',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    $regular = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Regular Marker',
    ]);

    $html = Livewire::test('pages::puzzles.index')->html();

    $startPos = strpos($html, "puzzle-card-{$regular->id}");
    expect($startPos)->not->toBeFalse();

    // Look at the slice of HTML for this card up to the next card's marker
    // (or end of document) — that slice should not contain the header phrase.
    $nextCardPos = strpos($html, 'puzzle-card-', $startPos + 20);
    $slice = $nextCardPos === false
        ? substr($html, $startPos)
        : substr($html, $startPos, $nextCardPos - $startPos);

    expect($slice)->not->toContain('Puzzle of the Day');
});

test('grid still renders pinned daily when no other puzzles exist', function () {
    Cache::flush();
    $creator = User::factory()->create();

    $daily = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Solo Daily Puzzle',
    ]);
    DailyPuzzle::create([
        'date' => today()->toDateString(),
        'crossword_id' => $daily->id,
    ]);

    Livewire::test('pages::puzzles.index')
        ->assertSeeHtml("puzzle-card-{$daily->id}")
        ->assertDontSee('No puzzles found');
});

test('minimum rating filter combines with other filters', function () {
    $creator = User::factory()->create();

    $match = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy Top Rated',
        'difficulty_label' => 'Easy',
    ]);
    PuzzleComment::factory()->create(['crossword_id' => $match->id, 'rating' => 5]);

    $noRating = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Easy No Rating',
        'difficulty_label' => 'Easy',
    ]);

    $wrongDifficulty = Crossword::factory()->published()->for($creator)->create([
        'title' => 'Hard Top Rated',
        'difficulty_label' => 'Hard',
    ]);
    PuzzleComment::factory()->create(['crossword_id' => $wrongDifficulty->id, 'rating' => 5]);

    Livewire::test('pages::puzzles.index')
        ->set('difficulty', 'Easy')
        ->set('minRating', '4')
        ->assertSee('Easy Top Rated')
        ->assertDontSee('Easy No Rating')
        ->assertDontSee('Hard Top Rated');
});
