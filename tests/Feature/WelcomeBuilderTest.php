<?php

use App\Enums\PuzzleType;
use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Livewire\Livewire;

test('welcome page renders the solve/build toggle and builder component', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee("tab = 'solve'", false)
        ->assertSee("tab = 'build'", false)
        ->assertSeeLivewire('welcome-builder');
});

test('welcome builder renders all puzzle type options', function () {
    Livewire::test('welcome-builder')
        ->assertSee(PuzzleType::Standard->label())
        ->assertSee(PuzzleType::Diamond->label())
        ->assertSee(PuzzleType::Freestyle->label());
});

test('cta label is "Start building" for everyone', function () {
    Livewire::test('welcome-builder')
        ->assertSee('Start building');

    Livewire::actingAs(User::factory()->create())
        ->test('welcome-builder')
        ->assertSee('Start building');
});

test('guest creating a puzzle creates an anonymous user and a crossword', function () {
    expect(User::query()->where('is_anonymous', true)->count())->toBe(0);

    Livewire::test('welcome-builder')
        ->set('puzzleType', PuzzleType::Standard->value)
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->call('createPuzzle');

    $anon = User::query()->where('is_anonymous', true)->first();
    expect($anon)->not->toBeNull();
    expect($anon->email)->toBeNull();
    expect($anon->password)->toBeNull();
    expect($anon->anonymous_token)->not->toBeNull();
    expect($anon->anonymous_created_at)->not->toBeNull();
    expect($anon->crosswords()->count())->toBe(1);
});

test('authenticated user can create a puzzle from the welcome builder', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('welcome-builder')
        ->set('puzzleType', PuzzleType::Standard->value)
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->call('createPuzzle');

    expect($user->crosswords()->count())->toBe(1);

    $crossword = $user->crosswords()->first();
    expect($crossword->width)->toBe(15);
    expect($crossword->height)->toBe(15);
    expect($crossword->puzzle_type)->toBe(PuzzleType::Standard);
});

test('switching to diamond type forces square odd dimensions', function () {
    Livewire::test('welcome-builder')
        ->set('newWidth', 14)
        ->set('puzzleType', PuzzleType::Diamond->value)
        ->assertSet('newWidth', 15)
        ->assertSet('newHeight', 15);
});

test('diamond puzzle creation works for guest', function () {
    Livewire::test('welcome-builder')
        ->set('puzzleType', PuzzleType::Diamond->value)
        ->set('newWidth', 15)
        ->set('newHeight', 15)
        ->call('createPuzzle');

    $anon = User::query()->where('is_anonymous', true)->first();
    expect($anon->crosswords()->first()->puzzle_type)->toBe(PuzzleType::Diamond);
});

test('anonymous user is capped at one puzzle', function () {
    $manager = app(AnonymousUserManager::class);
    $anon = $manager->create();
    Crossword::factory()->for($anon)->create();

    $component = Livewire::actingAs($anon)
        ->test('welcome-builder')
        ->call('createPuzzle')
        ->assertHasErrors('newWidth');

    expect($component->errors()->first('newWidth'))
        ->toContain('Create a free account');
});

test('builder shows the sign-up prompt and reports the limit for a capped guest', function () {
    $manager = app(AnonymousUserManager::class);
    $anon = $manager->create();
    Crossword::factory()->for($anon)->create();

    Livewire::actingAs($anon)
        ->test('welcome-builder')
        ->assertSee('Create a free account to build more puzzles.')
        ->assertSee('disabled');
});

test('builder is not at the limit for a fresh visitor', function () {
    Livewire::test('welcome-builder')
        ->assertDontSee('Create a free account to build more puzzles.');
});

test('create is blocked when free user is at puzzle limit', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->count($user->planLimits()->maxPuzzles())->create();

    Livewire::actingAs($user)
        ->test('welcome-builder')
        ->call('createPuzzle')
        ->assertHasErrors('newWidth');
});
