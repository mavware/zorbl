<?php

use App\Models\User;

/*
 * The dashboard page was split into the Build (crosswords.index) and Solve
 * (crosswords.solving) tabs. The route name lives on as a redirect so old
 * links and post-login redirects keep working.
 */

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('the dashboard route redirects authenticated users to the build page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('crosswords.index', absolute: false));
});
