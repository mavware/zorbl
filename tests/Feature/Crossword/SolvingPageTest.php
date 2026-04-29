<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('solving page shows user attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Diamond Puzzle']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertSee('Diamond Puzzle')
        ->assertSee($creator->name);
});

test('solving page shows available published puzzles via discovery component', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Public Puzzle']);
    Crossword::factory()->for($creator)->create(['title' => 'Private Puzzle']);

    $this->actingAs($user);

    Livewire::test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('Public Puzzle')
        ->assertDontSee('Private Puzzle');
});

test('discovery component shows own published puzzles', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->create(['title' => 'My Own Puzzle']);

    $this->actingAs($user);

    Livewire::test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertSee('My Own Puzzle');
});

test('discovery component does not show already attempted puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Already Started']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    $this->actingAs($user);

    Livewire::test('puzzle-discovery', ['excludeAttempted' => true])
        ->assertDontSee('Already Started');
});

test('user can start solving a published puzzle via discovery component', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('puzzle-discovery', ['excludeAttempted' => true])
        ->call('startSolving', $crossword->id)
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('user cannot start solving an unpublished puzzle they do not own', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('puzzle-discovery')
        ->call('startSolving', $crossword->id)
        ->assertForbidden();
});

test('user can remove an attempt', function () {
    $user = User::factory()->create();
    $attempt = PuzzleAttempt::factory()->for($user)->create();

    $this->actingAs($user);

    expect(PuzzleAttempt::count())->toBe(1);

    Livewire::test('pages::crosswords.solving')
        ->call('removeAttempt', $attempt->id);

    expect(PuzzleAttempt::count())->toBe(0);
});

test('user cannot remove another users attempt', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $attempt = PuzzleAttempt::factory()->for($other)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->call('removeAttempt', $attempt->id)
        ->assertStatus(403);
});

test('discovery component search filters available puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Ocean Theme']);
    Crossword::factory()->published()->for($creator)->create(['title' => 'Space Theme']);

    $this->actingAs($user);

    Livewire::test('puzzle-discovery', ['excludeAttempted' => true])
        ->set('search', 'Ocean')
        ->assertSee('Ocean Theme')
        ->assertDontSee('Space Theme');
});

test('filter shows only in-progress attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $inProgress = Crossword::factory()->published()->for($creator)->create(['title' => 'Unfinished Work']);
    $completed = Crossword::factory()->published()->for($creator)->create(['title' => 'Done Deal']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $inProgress->id, 'is_completed' => false]);
    PuzzleAttempt::factory()->for($user)->completed()->create(['crossword_id' => $completed->id]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->set('filter', 'in_progress')
        ->assertSee('Unfinished Work')
        ->assertDontSee('Done Deal');
});

test('filter shows only completed attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $inProgress = Crossword::factory()->published()->for($creator)->create(['title' => 'Unfinished Work']);
    $completed = Crossword::factory()->published()->for($creator)->create(['title' => 'Done Deal']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $inProgress->id, 'is_completed' => false]);
    PuzzleAttempt::factory()->for($user)->completed()->create(['crossword_id' => $completed->id]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->set('filter', 'completed')
        ->assertSee('Done Deal')
        ->assertDontSee('Unfinished Work');
});

test('all filter shows both in-progress and completed attempts', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $inProgress = Crossword::factory()->published()->for($creator)->create(['title' => 'Unfinished Work']);
    $completed = Crossword::factory()->published()->for($creator)->create(['title' => 'Done Deal']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $inProgress->id, 'is_completed' => false]);
    PuzzleAttempt::factory()->for($user)->completed()->create(['crossword_id' => $completed->id]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->set('filter', '')
        ->assertSee('Unfinished Work')
        ->assertSee('Done Deal');
});

test('search filters attempts by puzzle title', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $matching = Crossword::factory()->published()->for($creator)->create(['title' => 'Ocean Voyage']);
    $other = Crossword::factory()->published()->for($creator)->create(['title' => 'Space Adventure']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $matching->id]);
    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $other->id]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->set('search', 'Ocean')
        ->assertSee('Ocean Voyage')
        ->assertDontSee('Space Adventure');
});

test('attempt counts reflect active filters', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $c1 = Crossword::factory()->published()->for($creator)->create();
    $c2 = Crossword::factory()->published()->for($creator)->create();
    $c3 = Crossword::factory()->published()->for($creator)->create();

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $c1->id, 'is_completed' => false]);
    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $c2->id, 'is_completed' => false]);
    PuzzleAttempt::factory()->for($user)->completed()->create(['crossword_id' => $c3->id]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solving');
    $counts = $component->get('attemptCounts');

    expect($counts['all'])->toBe(3)
        ->and($counts['in_progress'])->toBe(2)
        ->and($counts['completed'])->toBe(1);
});

test('sort by oldest shows oldest first', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $older = Crossword::factory()->published()->for($creator)->create(['title' => 'Old Puzzle']);
    $newer = Crossword::factory()->published()->for($creator)->create(['title' => 'New Puzzle']);

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $older->id,
        'updated_at' => now()->subDays(5),
    ]);
    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $newer->id,
        'updated_at' => now(),
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solving')
        ->set('sortBy', 'oldest');

    $attempts = $component->get('attempts');

    expect($attempts->first()->crossword->title)->toBe('Old Puzzle')
        ->and($attempts->last()->crossword->title)->toBe('New Puzzle');
});

test('sort by fastest shows fastest completed first', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $fast = Crossword::factory()->published()->for($creator)->create(['title' => 'Quick One']);
    $slow = Crossword::factory()->published()->for($creator)->create(['title' => 'Slow Burn']);

    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $fast->id,
        'solve_time_seconds' => 60,
    ]);
    PuzzleAttempt::factory()->for($user)->completed()->create([
        'crossword_id' => $slow->id,
        'solve_time_seconds' => 600,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solving')
        ->set('sortBy', 'fastest');

    $attempts = $component->get('attempts');

    expect($attempts->first()->crossword->title)->toBe('Quick One')
        ->and($attempts->last()->crossword->title)->toBe('Slow Burn');
});

test('empty state changes text when filters are active', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertSee('No puzzles in progress')
        ->set('filter', 'completed')
        ->assertSee('No matching puzzles');
});
