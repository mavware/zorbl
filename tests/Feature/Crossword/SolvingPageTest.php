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

test('solving page shows available published puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Public Puzzle']);
    Crossword::factory()->for($creator)->create(['title' => 'Private Puzzle']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertSee('Public Puzzle')
        ->assertDontSee('Private Puzzle');
});

test('solving page does not show own puzzles in browse section', function () {
    $user = User::factory()->create();
    Crossword::factory()->published()->for($user)->create(['title' => 'My Own Puzzle']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->assertDontSee('My Own Puzzle');
});

test('solving page does not show already attempted puzzles in browse section', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['title' => 'Already Started']);

    PuzzleAttempt::factory()->for($user)->create(['crossword_id' => $crossword->id]);

    $this->actingAs($user);

    // Should appear in attempts but not in browse
    Livewire::test('pages::crosswords.solving')
        ->assertSee('Already Started')
        ->assertDontSee('Start Solving');
});

test('user can start solving a published puzzle', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->call('startSolving', $crossword->id)
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('user cannot start solving an unpublished puzzle they do not own', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
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

test('solving page search filters available puzzles', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    Crossword::factory()->published()->for($creator)->create(['title' => 'Ocean Theme']);
    Crossword::factory()->published()->for($creator)->create(['title' => 'Space Theme']);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solving')
        ->set('search', 'Ocean')
        ->assertSee('Ocean Theme')
        ->assertDontSee('Space Theme');
});
