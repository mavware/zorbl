<?php

use App\Enums\Theme;

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

    $svgIcon = collect($manifest['icons'])->firstWhere('src', '/logo-modern.svg');
    expect($svgIcon)->not->toBeNull()
        ->and($svgIcon['type'])->toBe('image/svg+xml');

    foreach (collect($manifest['icons'])->pluck('src') as $src) {
        expect(is_file(public_path(ltrim($src, '/'))))->toBeTrue("Manifest icon {$src} is missing from public/");
    }
});

test('favicon assets exist in the public root', function () {
    expect(is_file(public_path('logo.svg')))->toBeTrue()
        ->and(is_file(public_path('logo-modern.svg')))->toBeTrue()
        ->and(is_file(public_path('favicon.ico')))->toBeTrue()
        ->and(is_file(public_path('apple-touch-icon.png')))->toBeTrue();
});

test('pages declare the svg favicon with ico and apple-touch-icon fallbacks', function (string $url) {
    $this->get($url)
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.asset(Theme::current()->logoFile()).'" type="image/svg+xml">', false)
        ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" sizes="32x32">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'" sizes="180x180">', false);
})->with([
    'welcome' => '/',
    'public layout' => fn () => route('puzzles.index'),
]);

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

test('install prompt partial is not included in public layout pages', function () {
    $response = $this->get(route('puzzles.index'));

    $response->assertOk()
        ->assertDontSee('crosswordbuilderPwa', false)
        ->assertDontSee('install-banner-title', false);
});
