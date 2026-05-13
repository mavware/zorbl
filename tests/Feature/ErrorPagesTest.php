<?php

test('hitting a non-existent route renders the styled 404', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound()
        ->assertSee('missing a few squares')
        ->assertSee('404', false)
        ->assertSee('Browse puzzles');
});

test('404 page has the brand link, robots noindex, and matches dark theme', function () {
    $html = $this->get('/this-route-does-not-exist')->getContent();

    expect($html)
        ->toContain('noindex')
        ->toContain('color-scheme: dark')
        ->toContain((string) config('app.name'))
        ->toContain('href="'.url('/').'"');
});

test('error pages do not pull in Vite assets', function () {
    $html = $this->get('/this-route-does-not-exist')->getContent();

    // Pure inline CSS — a broken Vite manifest at deploy time cannot break the error page.
    expect($html)->not->toContain('/build/');
});

test('500 view renders the styled server-error page', function () {
    $html = view('errors.500', ['exception' => new Exception('boom')])->render();

    expect($html)
        ->toContain('Something went sideways on our end.')
        ->toContain('500')
        ->toContain('Try again');
});

test('403 view renders the styled forbidden page', function () {
    $html = view('errors.403', ['exception' => new Exception])->render();

    expect($html)
        ->toContain('not allowed to see this')
        ->toContain('403');
});

test('419 view renders the styled page-expired page', function () {
    $html = view('errors.419', ['exception' => new Exception])->render();

    expect($html)
        ->toContain('Your session timed out.')
        ->toContain('419')
        ->toContain('Reload page');
});

test('503 view renders the styled maintenance page', function () {
    $html = view('errors.503', ['exception' => new Exception])->render();

    expect($html)
        ->toContain('shipping something good')
        ->toContain('503');
});

test('429 view renders the styled too-many-requests page', function () {
    $html = view('errors.429', ['exception' => new Exception])->render();

    expect($html)
        ->toContain('Slow down a moment.')
        ->toContain('429');
});
