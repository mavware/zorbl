<?php

use App\Models\Crossword;
use App\Models\FavoriteList;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('owner can solve their own puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSet('isOwner', true);

    // PuzzleAttempt should be created automatically
    expect(PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->exists())->toBeTrue();
});

test('user can solve a published puzzle by another creator', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSet('isOwner', false);
});

test('user cannot solve an unpublished puzzle by another creator', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertForbidden();
});

test('solver saves progress to puzzle attempt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);
    $progress[0][0] = 'A';

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->progress[0][0])->toBe('A')
        ->and($attempt->is_completed)->toBeFalse();
});

test('solver marks attempt as completed', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    $progress = Crossword::emptySolution(3, 3);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('saveProgress', $progress, true);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->is_completed)->toBeTrue();
});

test('solver resumes existing attempt progress', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    $progress = Crossword::emptySolution(3, 3);
    $progress[1][1] = 'X';

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'progress' => $progress,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('progress.1.1', 'X');
});

test('solver hides edit button for non-owners', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('Edit');
});

test('solver shows edit button for owners', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('Edit');
});

test('solver shows add to favorites button for non-owners', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSeeHtml('Add to favorites list');
});

test('solver does not show add to favorites button for owners', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSeeHtml('Add to favorites list');
});

test('user can add puzzle to existing favorite list from solver', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();
    $list = FavoriteList::create(['user_id' => $user->id, 'name' => 'My List']);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('addToList', $list->id);

    expect($list->crosswords()->where('crossword_id', $crossword->id)->exists())->toBeTrue();
});

test('user can create new list and add puzzle from solver', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('newListName', 'Weekend Puzzles')
        ->call('createListAndAdd');

    $list = FavoriteList::where('user_id', $user->id)->where('name', 'Weekend Puzzles')->first();
    expect($list)->not->toBeNull()
        ->and($list->crosswords()->where('crossword_id', $crossword->id)->exists())->toBeTrue();
});

test('saveProgress enforces PuzzleAttemptPolicy update check', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 2, 'height' => 2]);

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword]);

    Gate::before(function ($authUser, $ability, $arguments) {
        if ($ability === 'update' && isset($arguments[0]) && $arguments[0] instanceof PuzzleAttempt) {
            return false;
        }
    });

    $component->call('saveProgress', [['Z', ''], ['', '']], false, 10)
        ->assertForbidden();

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->progress[0][0])->not->toBe('Z');
});

test('adding puzzle to list is idempotent', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create();
    $list = FavoriteList::create(['user_id' => $user->id, 'name' => 'My List']);
    $list->crosswords()->attach($crossword->id);

    Livewire::actingAs($user)
        ->test('pages::crosswords.solver', ['crossword' => $crossword])
        ->call('addToList', $list->id);

    expect($list->crosswords()->where('crossword_id', $crossword->id)->count())->toBe(1);
});

test('solver loads cell background colors from styles', function () {
    $user = User::factory()->create();
    $styles = [
        '0,0' => ['color' => '#FECACA'],
        '1,1' => ['color' => '#BAE6FD', 'shapebg' => 'circle'],
    ];
    $crossword = Crossword::factory()->published()->for($user)->create([
        'styles' => $styles,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSet('styles', $styles);
});

test('solver renders share button when puzzle is solved', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['width' => 3, 'height' => 3]);

    PuzzleAttempt::factory()->for($user)->create([
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'solve_time_seconds' => 120,
        'completed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSet('isSolved', true)
        ->assertSeeHtml('shareResults()')
        ->assertSeeHtml('shareCopied');
});

test('solver renders share button in celebration modal', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($user)->create(['width' => 3, 'height' => 3]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSeeHtml('shareCopied ?')
        ->assertSeeHtml('shareResults()');
});

test('solver hides embed UI from non-owners when allow_embed is off', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['allow_embed' => false]);
    $player = User::factory()->create();

    $this->actingAs($player);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertDontSeeHtml('Embed Puzzle');
});

test('solver shows embed UI to non-owners when allow_embed is on', function () {
    $creator = User::factory()->create();
    $crossword = Crossword::factory()->published()->for($creator)->create(['allow_embed' => true]);
    $player = User::factory()->create();

    $this->actingAs($player);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSeeHtml('Embed Puzzle');
});

test('solver shows embed UI to owner regardless of allow_embed', function () {
    $owner = User::factory()->create();
    // Unpublished + allow_embed off: owner still sees their own embed code.
    $crossword = Crossword::factory()->for($owner)->create([
        'is_published' => false,
        'allow_embed' => false,
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertOk()
        ->assertSeeHtml('Embed Puzzle');
});

test('solver loads puzzle-wide default colors from metadata', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'metadata' => ['colors' => [
            'cell' => '#FEF08A',
            'block' => '#1E293B',
            'circle' => '#DB2777',
            'letter' => '#2563EB',
            'line' => '#0F766E',
        ]],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('defaultColors', [
            'cell' => '#FEF08A',
            'block' => '#1E293B',
            'circle' => '#DB2777',
            'letter' => '#2563EB',
            'line' => '#0F766E',
        ]);
});

test('solver default colors are empty when none are set', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['metadata' => null]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('defaultColors', []);
});
