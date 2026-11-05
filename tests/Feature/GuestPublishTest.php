<?php

use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Livewire\Livewire;

function makeGuestBuilder(): User
{
    return app(AnonymousUserManager::class)->create();
}

test('a guest can publish from the editor and is offered the public link', function () {
    $guest = makeGuestBuilder();
    $crossword = Crossword::factory()->for($guest)->create(['is_published' => false]);

    $this->actingAs($guest);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true)
        ->assertSet('showGuestShareModal', true)
        ->assertSee('Your puzzle is live')
        ->assertSee('Create free account');

    expect($crossword->fresh()->is_published)->toBeTrue();
});

test('a registered user publishing does not get the guest share prompt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create(['is_published' => false]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->call('togglePublished')
        ->assertSet('isPublished', true)
        ->assertSet('showGuestShareModal', false);
});

test('the editor shows a guest the real publish button instead of a sign-up link', function () {
    $guest = makeGuestBuilder();
    $crossword = Crossword::factory()->for($guest)->create(['is_published' => false]);

    $this->actingAs($guest)
        ->get(route('crosswords.editor', $crossword))
        ->assertOk()
        ->assertSee('data-test="editor-publish-button"', false)
        ->assertDontSee('Sign up to publish');
});

test('a puzzle published by a guest is solvable by the public', function () {
    $guest = makeGuestBuilder();
    $crossword = Crossword::factory()->for($guest)->published()->create();

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk();
});

test('pruning keeps idle guests who have a published puzzle', function () {
    $idleWithPublished = makeGuestBuilder();
    $idleWithPublished->forceFill(['last_seen_at' => now()->subDays(45)])->saveQuietly();
    $published = Crossword::factory()->for($idleWithPublished)->published()->create();

    $idleWithDraft = makeGuestBuilder();
    $idleWithDraft->forceFill(['last_seen_at' => now()->subDays(45)])->saveQuietly();
    $draft = Crossword::factory()->for($idleWithDraft)->create(['is_published' => false]);

    $this->artisan('users:prune-anonymous', ['--days' => 30])
        ->expectsOutputToContain('Pruned 1 anonymous user(s)')
        ->assertSuccessful();

    expect(User::find($idleWithPublished->id))->not->toBeNull()
        ->and(Crossword::find($published->id))->not->toBeNull()
        ->and(User::find($idleWithDraft->id))->toBeNull()
        ->and(Crossword::find($draft->id))->toBeNull();
});
