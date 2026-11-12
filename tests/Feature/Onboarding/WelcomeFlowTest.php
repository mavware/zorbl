<?php

use App\Models\Crossword;
use App\Models\User;
use App\Notifications\WelcomeEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

test('Fortify registration sends a welcome email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'ada@example.test')->firstOrFail();
    Notification::assertSentTo($user, WelcomeEmail::class);
});

test('the Registered event triggers the WelcomeEmail listener once', function () {
    Notification::fake();
    $user = User::factory()->create();

    Event::dispatch(new Registered($user));

    Notification::assertSentTo($user, WelcomeEmail::class);
    Notification::assertSentToTimes($user, WelcomeEmail::class, 1);
});

test('build page shows the empty state for a brand-new account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('No puzzles yet')
        ->assertSee('Create a new crossword or import an existing puzzle file.')
        ->assertSee('New Puzzle')
        ->assertSee('Import Puzzle');
});

test('build page hides the empty state once the user has a puzzle', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create();

    $this->actingAs($user)->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('No puzzles yet');
});

test('solving empty state shows a browse-puzzles CTA when no attempts exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSee('solving-empty-state', false)
        ->assertSee('Browse puzzles')
        ->assertSee(route('puzzles.index'), false);
});

test('solving empty-state CTA is hidden when filtering returned no results', function () {
    $user = User::factory()->create();
    // No attempts at all; trigger the "no matching" branch via a search term.
    $this->actingAs($user)->get(route('crosswords.solving', ['search' => 'nothing-here']))
        ->assertOk()
        ->assertSee('No matching puzzles')
        ->assertDontSee('Browse the community catalog');
});

test('favorites empty state shows a browse-puzzles CTA', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('favorites.index'))
        ->assertOk()
        ->assertSee('favorites-empty-state', false)
        ->assertSee('Browse puzzles')
        ->assertSee(route('puzzles.index'), false);
});
