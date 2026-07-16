<?php

use App\Models\Crossword;

test('regular pages emit baseline security headers', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=()')
        ->toContain('microphone=()')
        ->toContain('geolocation=()');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain('https://js.stripe.com')
        ->toContain("frame-ancestors 'self'")
        ->toContain("object-src 'none'");
});

test('public solver page emits security headers', function () {
    $crossword = Crossword::factory()->published()->create();

    $response = $this->get(route('puzzles.solve', $crossword));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

test('embed routes are exempt from frame restrictions so external sites can iframe them', function () {
    $crossword = Crossword::factory()->published()->create();

    $response = $this->get(route('embed.solver', $crossword));

    $response->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBeNull();
    expect($response->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *');
});

test('HSTS is not set on non-secure requests', function () {
    $response = $this->get('/');

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

test('Content-Security-Policy includes Sentry and Stripe ingest hosts in connect-src', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('https://api.stripe.com')
        ->toContain('sentry.io');
});

test('CSP allows fonts.bunny.net for fonts and styles', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain('https://fonts.bunny.net')
        ->toContain("font-src 'self' data: https://fonts.bunny.net");
});

test('CSP whitelists the Vite dev server origin while it is running', function () {
    $hotFile = public_path('hot');
    $original = is_file($hotFile) ? file_get_contents($hotFile) : null;

    try {
        file_put_contents($hotFile, 'https://crosswordbuilder.test:5173');

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        // Dev server assets (scripts + styles) and its HMR WebSocket are allowed.
        expect($csp)
            ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com https://crosswordbuilder.test:5173")
            ->toContain("style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://crosswordbuilder.test:5173")
            ->toContain('wss://crosswordbuilder.test:5173');
    } finally {
        if ($original === null) {
            @unlink($hotFile);
        } else {
            file_put_contents($hotFile, $original);
        }
    }
});

test('CSP omits the Vite dev server origin when it is not running', function () {
    $hotFile = public_path('hot');
    $original = is_file($hotFile) ? file_get_contents($hotFile) : null;

    try {
        @unlink($hotFile);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        expect($csp)->not->toContain(':5173');
    } finally {
        if ($original !== null) {
            file_put_contents($hotFile, $original);
        }
    }
});
