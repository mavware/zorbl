<?php

use App\Models\User;

test('welcome page nav shows the logo followed by the app name', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            '<svg viewBox="0 0 32 32" role="img" aria-label="'.config('app.name').'" data-app-logo',
            '<span>'.config('app.name').'</span>',
        ], false);
});

test('dashboard brand shows the logo followed by the app name', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-flux-sidebar-brand',
            '<svg viewBox="0 0 32 32" role="img" aria-label="'.config('app.name').'" data-app-logo',
            config('app.name'),
        ], false);
});

test('the inline logo is drawn from the theme tokens rather than a fixed colour', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('fill: var(--logo-fill)', false)
        ->assertSee('font-family: var(--logo-font)', false)
        ->assertDontSee('<img src="'.asset('logo.svg').'"', false);
});
