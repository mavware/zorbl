<?php

use App\Enums\Theme;
use App\Models\User;

test('the classical theme is rendered by default', function () {
    config(['app.theme' => 'classical']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="classical"', false);
});

test('the configured theme is rendered on public and app layouts', function () {
    config(['app.theme' => 'modern']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="modern"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-theme="modern"', false);
});

test('an unknown theme falls back to classical', function (mixed $configured) {
    config(['app.theme' => $configured]);

    expect(Theme::current())->toBe(Theme::Classical);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="classical"', false);
})->with([
    'unknown name' => ['brutalist'],
    'null' => [null],
    'empty string' => [''],
]);
