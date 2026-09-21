<?php

use App\Models\Crossword;
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

test('the build page has a heading and no inline build/solve switch', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Build')
        ->assertDontSee('data-test="dashboard-switch"', false);
});

test('the solve page has a heading and no inline build/solve switch', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.solving'))
        ->assertOk()
        ->assertSee('Solve')
        ->assertDontSee('data-test="dashboard-switch"', false);
});

test('the sidebar lists build first and solve second instead of a dashboard item', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'href="'.route('crosswords.index').'"',
            'Build',
            'href="'.route('crosswords.solving').'"',
            'Solve',
        ], false)
        ->assertDontSee('>Dashboard<', false);
});

test('the sidebar separates its nav groups and the user menu with inset separators', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            route('favorites.index'),
            'data-flux-separator',
            route('clues.index'),
            route('support.index'),
            'data-flux-separator',
            'data-test="sidebar-menu-button"',
        ], false);
});

test('the sidebar marks build current on the build page and solve current on the solve page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertSeeInOrder(['href="'.route('crosswords.index').'"', 'data-current'], false);

    $this->actingAs($user)
        ->get(route('crosswords.solving'))
        ->assertSeeInOrder(['href="'.route('crosswords.solving').'"', 'data-current'], false);
});

test('the build page shows the welcome hero inside the header above the stats band for a new user', function () {
    $newUser = User::factory()->create();

    $this->actingAs($newUser)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder(['data-page-header-body', 'data-test="dashboard-welcome-hero"', 'data-page-header-footer', 'Total Solves'], false);

    $builder = User::factory()->create();
    Crossword::factory()->for($builder)->create();

    $this->actingAs($builder)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder(['data-page-header-footer', 'Total Solves'], false)
        ->assertDontSee('data-test="dashboard-welcome-hero"', false);
});
