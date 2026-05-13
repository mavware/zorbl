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

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
