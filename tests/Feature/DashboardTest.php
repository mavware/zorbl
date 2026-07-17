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

test('the build page titles itself with a switch to the solve page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-test="dashboard-switch"', false)
        ->assertSeeHtml('href="'.route('crosswords.solving').'"');
});

test('the solve page titles itself with a switch to the build page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSee('data-test="dashboard-switch"', false)
        ->assertSeeHtml('href="'.route('crosswords.index').'"');
});

test('the sidebar shows a single dashboard item for build and solve', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Dashboard');
});
