<?php

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\User;
use Livewire\Livewire;

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
        ->set('sortBy', 'oldest')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('gridSize', '')
        ->assertSet('puzzleType', '')
        ->assertSet('constructor', '')
        ->assertSet('dateRange', '')
        ->assertSet('difficulty', '')
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

    $component = Livewire::test('pages::puzzles.index');
    expect($component->instance()->puzzleTypeLabel($crossword))->toBe('Standard');
});

test('puzzle type label returns Shaped for grids with null cells', function () {
    $shapedGrid = Crossword::emptyGrid(15, 15);
    $shapedGrid[0][0] = null;
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'grid' => $shapedGrid,
    ]);

    $component = Livewire::test('pages::puzzles.index');
    expect($component->instance()->puzzleTypeLabel($crossword))->toBe('Shaped');
});

test('puzzle type label returns Barred for grids with bar styles', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create([
        'styles' => [['bars' => ['bottom']]],
    ]);

    $component = Livewire::test('pages::puzzles.index');
    expect($component->instance()->puzzleTypeLabel($crossword))->toBe('Barred');
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
