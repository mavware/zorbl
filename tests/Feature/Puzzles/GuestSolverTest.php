<?php

use App\Models\Crossword;
use App\Models\User;

test('guest can access solver for published crossword', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create([
        'title' => 'Guest Solve Test',
    ]);

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('Guest Solve Test')
        ->assertSee('zorblGuestPersistence')
        ->assertSee('zorblDecodeSolution');
});

test('guest gets 404 for unpublished crossword', function () {
    $crossword = Crossword::factory()->create(['is_published' => false]);

    $this->get(route('puzzles.solve', $crossword))
        ->assertNotFound();
});

test('authenticated user is redirected to full solver', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    $this->actingAs($user)
        ->get(route('puzzles.solve', $crossword))
        ->assertRedirect(route('crosswords.solver', $crossword));
});

test('guest solve cookie is set on first visit', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create();

    $response = $this->get(route('puzzles.solve', $crossword));

    $response->assertOk()
        ->assertCookie('zorbl_guest_solved');
});

test('guest can revisit the same puzzle', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create();

    // First visit sets the cookie
    $response = $this->withCookie('zorbl_guest_solved', json_encode([$crossword->id]))
        ->get(route('puzzles.solve', $crossword));

    $response->assertOk();
});

test('guest is redirected to register when trying a second puzzle', function () {
    $first = Crossword::factory()->published()->create();
    $second = Crossword::factory()->published()->create();

    $this->withCookie('zorbl_guest_solved', json_encode([$first->id]))
        ->get(route('puzzles.solve', $second))
        ->assertRedirect(route('register'));
});

test('guest solver page includes signup banner', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create();

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('Sign up');
});

test('guest solver page uses public layout', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->withSolution()->create();

    $this->get(route('puzzles.solve', $crossword))
        ->assertOk()
        ->assertSee('Browse Puzzles');
});
