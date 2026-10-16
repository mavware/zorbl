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

test('the sidebar lists its main destinations unbroken and separates the user menu with an inset separator', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            route('crosswords.solving'),
            route('clues.index'),
            route('support.index'),
            'data-flux-separator',
            'data-test="sidebar-menu-button"',
            route('favorites.index'),
        ], false)
        ->getContent();

    preg_match('/data-test="app-sidebar".*?href="'.preg_quote(route('clues.index'), '/').'"/s', $html, $buildToReference);

    expect($buildToReference[0])->not->toContain('data-flux-separator');
});

test('the top bar lists its main destinations without a separator', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->getContent();

    preg_match('/data-test="header-nav".*?<\/nav>/s', $html, $headerNav);

    expect($headerNav[0])
        ->toContain('href="'.route('clues.index').'"')
        ->not->toContain('data-flux-separator');
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

test('the build page header has no welcome hero or stats band', function () {
    $newUser = User::factory()->create();

    $this->actingAs($newUser)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('data-test="dashboard-welcome-hero"', false)
        ->assertDontSee('data-page-header-footer', false);

    $builder = User::factory()->create();
    Crossword::factory()->for($builder)->create();

    $this->actingAs($builder)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('data-test="dashboard-welcome-hero"', false)
        ->assertDontSee('data-page-header-footer', false);
});

test('the page header kicker is hidden on phones', function () {
    $user = User::factory()->create();
    Crossword::factory()->for($user)->create();

    $html = $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->getContent();

    preg_match('/<div[^>]*data-test="page-header-kicker"[^>]*>Your workshop<\/div>/', $html, $kicker);

    expect($kicker)->not->toBeEmpty()
        ->and($kicker[0])->toContain('hidden')
        ->and($kicker[0])->toContain('sm:block');
});

test('the build page import button shortens its label on phones', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->getContent();

    preg_match('/<button[^>]*data-test="build-import-button"[^>]*>.*?<\/button>/s', $html, $button);

    expect($button)->not->toBeEmpty()
        ->and(preg_replace('/\s+/', ' ', $button[0]))
        ->toContain('Import<span class="max-sm:hidden"> Puzzle</span>');
});

test('the build page header actions stay on one row on phones', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-test="page-header-actions"',
            'New Puzzle',
            'data-test="build-import-button"',
            'data-test="build-analytics-button"',
        ], false);

    preg_match('/<div[^>]*data-test="page-header-actions"[^>]*>/', $this->get(route('crosswords.index'))->getContent(), $actions);

    expect($actions[0])->toContain('max-sm:flex-nowrap')->toContain('max-sm:w-full');
});
