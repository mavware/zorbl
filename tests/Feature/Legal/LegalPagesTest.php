<?php

use App\Models\User;

test('terms page renders for guests', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('Eligibility')
        ->assertSee('Your Content')
        ->assertSee('Limitation of Liability');
});

test('privacy page renders for guests', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('What We Collect')
        ->assertSee('Your Rights')
        ->assertSee('Children');
});

test('cookie policy page renders for guests', function () {
    $this->get(route('legal.cookies'))
        ->assertOk()
        ->assertSee('Cookie Policy')
        ->assertSee('Strictly necessary')
        ->assertSee('XSRF-TOKEN');
});

test('dmca page renders for guests and shows the takedown elements', function () {
    $this->get(route('legal.dmca'))
        ->assertOk()
        ->assertSee('Copyright (DMCA) Policy')
        ->assertSee('Filing a takedown notice')
        ->assertSee('Filing a counter-notice')
        ->assertSee('Log in to use the support form');
});

test('dmca page surfaces a direct support form link for logged-in users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('legal.dmca'))
        ->assertOk()
        ->assertSee('Submit a DMCA notice')
        ->assertSee(route('support.create', ['category' => 'copyright']), false);
});

test('legal pages are reachable for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('legal.terms'))->assertOk();
    $this->actingAs($user)->get(route('legal.privacy'))->assertOk();
    $this->actingAs($user)->get(route('legal.cookies'))->assertOk();
    $this->actingAs($user)->get(route('legal.dmca'))->assertOk();
});

test('public footer links to all legal pages', function () {
    $response = $this->get(route('puzzles.index'));

    $response->assertOk()
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false)
        ->assertSee(route('legal.cookies'), false)
        ->assertSee(route('legal.dmca'), false);
});

test('support form accepts the copyright category', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::support.create')
        ->set('subject', 'Copyright takedown for Puzzle 42')
        ->set('description', 'This puzzle reuses my copyrighted clue without permission. Details follow…')
        ->set('category', 'copyright')
        ->call('submit')
        ->assertHasNoErrors();

    expect($user->supportTickets()->first()->category)->toBe('copyright');
});

test('support form prefills category from a query string', function () {
    $user = User::factory()->create();

    Livewire::withQueryParams(['category' => 'copyright'])
        ->actingAs($user)
        ->test('pages::support.create')
        ->assertSet('category', 'copyright');
});
