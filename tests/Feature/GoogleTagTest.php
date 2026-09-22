<?php

use App\Models\User;

$gtagScript = '<script async src="https://www.googletagmanager.com/gtag/js?id=AW-18331227740"></script>';

test('the google tag appears exactly once on every layout', function (string $route, bool $authenticated) use ($gtagScript) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $html = $this->get(route($route))->assertOk()->getContent();

    expect(substr_count($html, $gtagScript))->toBe(1)
        ->and(substr_count($html, "gtag('config', 'AW-18331227740')"))->toBe(1);
})->with([
    'welcome page' => ['home', false],
    'public layout' => ['tools.convert', false],
    'auth layout' => ['login', false],
    'app layout' => ['crosswords.index', true],
]);

test('the google tag is not injected into embedded puzzles or error pages', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertDontSee('googletagmanager.com/gtag', false);
});
