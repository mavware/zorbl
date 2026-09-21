<?php

use App\Models\User;

test('welcome page nav shows the logo followed by the app name', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder([
            '<img src="'.asset('logo.svg').'"',
            '<span>'.config('app.name').'</span>',
        ], false);
});

test('dashboard brand shows the logo followed by the app name', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-flux-sidebar-brand',
            '<img src="'.asset('logo.svg').'"',
            config('app.name'),
        ], false);
});
