<?php

test('site.webmanifest exists in public root with all PWA fields', function () {
    $path = public_path('site.webmanifest');
    expect(is_file($path))->toBeTrue();

    $manifest = json_decode((string) file_get_contents($path), true);

    expect($manifest)
        ->toHaveKey('name')
        ->toHaveKey('short_name')
        ->toHaveKey('start_url')
        ->toHaveKey('scope')
        ->toHaveKey('display', 'standalone')
        ->toHaveKey('theme_color')
        ->toHaveKey('background_color');

    expect($manifest['icons'])->toBeArray()->not->toBeEmpty();

    // Chrome requires 192 and 512 icons for installability.
    $sizes = collect($manifest['icons'])->pluck('sizes');
    expect($sizes)->toContain('192x192')->toContain('512x512');
});

test('service worker exists at the site root with the three required handlers', function () {
    $path = public_path('service-worker.js');
    expect(is_file($path))->toBeTrue();

    $body = (string) file_get_contents($path);
    expect($body)
        ->toContain("addEventListener('install'")
        ->toContain("addEventListener('activate'")
        ->toContain("addEventListener('fetch'");
});

test('offline fallback page exists and renders a recovery action', function () {
    $path = public_path('offline.html');
    expect(is_file($path))->toBeTrue();

    $body = (string) file_get_contents($path);
    expect($body)
        ->toContain('offline')
        ->toContain('Try again');
});

test('welcome page references manifest and apple PWA meta', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('site.webmanifest', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('apple-mobile-web-app-title', false);
});

test('public layout pages reference the manifest', function () {
    $response = $this->get(route('puzzles.index'));

    $response->assertOk()
        ->assertSee('site.webmanifest', false)
        ->assertSee('apple-mobile-web-app-capable', false);
});

test('install prompt partial is included in the welcome page', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('zorblPwa', false)
        ->assertSee('install-banner-title', false);
});
