<?php

use App\Enums\Theme;
use App\Models\User;

test('the modern theme is rendered by default', function () {
    config(['app.theme' => 'modern']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="modern"', false);
});

test('the configured theme is rendered on public and app layouts', function () {
    config(['app.theme' => 'classical']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="classical"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-theme="classical"', false);
});

test('an unknown theme falls back to modern', function (mixed $configured) {
    config(['app.theme' => $configured]);

    expect(Theme::current())->toBe(Theme::Modern);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="modern"', false);
})->with([
    'unknown name' => ['brutalist'],
    'null' => [null],
    'empty string' => [''],
]);

test('the svg favicon follows the configured theme', function (string $theme, string $file) {
    config(['app.theme' => $theme]);

    expect(Theme::from($theme)->logoFile())->toBe($file);

    $this->get('/')
        ->assertOk()
        ->assertSee('<link rel="icon" href="'.asset($file).'" type="image/svg+xml">', false);
})->with([
    'classical' => ['classical', 'logo.svg'],
    'modern' => ['modern', 'logo-modern.svg'],
]);
