<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen renders with clickwrap terms acknowledgement', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee('By creating an account')
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false);
});

test('registration screen has no confirm-password field', function () {
    $html = $this->get(route('register'))->getContent();

    expect($html)->not->toContain('password_confirmation');
});

test('new users can register without confirming their password', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        // intentionally no password_confirmation
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('crosswords.index', absolute: false));

    $this->assertAuthenticated();
});
