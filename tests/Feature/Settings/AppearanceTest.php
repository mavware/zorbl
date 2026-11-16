<?php

use App\Models\User;

test('pages start visitors with no stored preference on dark before flux applies the appearance', function (string $routeName, bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route($routeName))
        ->assertOk()
        ->assertSeeInOrder([
            "window.localStorage.setItem('flux.appearance', 'dark')",
            'window.Flux.applyAppearance(',
        ], false);
})->with([
    'welcome page' => ['home', false],
    'public layout' => ['tools.convert', false],
    'auth layout' => ['login', false],
    'app layout' => ['appearance.edit', true],
]);

test('choosing an appearance in settings records the choice so system is not reset to dark', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee("window.localStorage.setItem('appearance.chosen', '1')", false);
});
