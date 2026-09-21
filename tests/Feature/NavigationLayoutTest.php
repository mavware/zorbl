<?php

use App\Enums\Navigation;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Laravel\Cashier\Subscription;
use Spatie\Permission\Models\Role;

/**
 * Every destination the app chrome must offer, regardless of which chrome
 * (sidebar or top bar) is configured.
 *
 * @return array<int, string>
 */
function expectedNavigationLinks(bool $registered, bool $admin = false): array
{
    return array_values(array_filter([
        route('crosswords.index'),
        route('crosswords.solving'),
        $registered ? route('favorites.index') : null,
        route('clues.index'),
        route('words.index'),
        route('constructors.index'),
        route('contests.index'),
        route('help.index'),
        route('support.index'),
        $admin ? route('filament.admin.home') : null,
        route('legal.terms'),
        route('legal.privacy'),
        route('legal.cookies'),
        route('legal.dmca'),
    ]));
}

test('the sidebar chrome is rendered by default', function () {
    config(['crosswordbuilder.navigation' => 'sidebar']);

    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-test="sidebar-menu-button"', false)
        ->assertDontSee('data-test="header-nav"', false);
});

test('the top bar chrome is rendered when configured', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-test="header-nav"', false)
        ->assertSee('data-test="header-menu-button"', false)
        ->assertSee('data-test="header-help-menu"', false)
        ->assertDontSee('data-test="sidebar-menu-button"', false);
});

test('an unknown navigation config falls back to the sidebar', function (mixed $configured) {
    config(['crosswordbuilder.navigation' => $configured]);

    expect(Navigation::current())->toBe(Navigation::Sidebar);

    $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-test="sidebar-menu-button"', false)
        ->assertDontSee('data-test="header-nav"', false);
})->with([
    'unknown name' => ['drawer'],
    'null' => [null],
    'empty string' => [''],
]);

test('the navigation enum maps each chrome to its layout component', function () {
    expect(Navigation::Sidebar->layoutComponent())->toBe('layouts::app.sidebar')
        ->and(Navigation::Header->layoutComponent())->toBe('layouts::app.header');
});

test('both chromes offer a registered user the same links', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('Log out', false)
        ->assertSee(route('billing.index'), false)
        ->assertSee('Support our work', false)
        ->assertDontSee(route('register'), false);

    foreach (expectedNavigationLinks(registered: true) as $href) {
        $response->assertSee('href="'.$href.'"', false);
    }
})->with(['sidebar', 'header']);

test('both chromes offer an admin the admin link', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $response = $this->actingAs($admin)
        ->get(route('crosswords.index'))
        ->assertOk();

    foreach (expectedNavigationLinks(registered: true, admin: true) as $href) {
        $response->assertSee('href="'.$href.'"', false);
    }
})->with(['sidebar', 'header']);

test('both chromes offer a guest a sign up button and no account links', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $anon = app(AnonymousUserManager::class)->create();

    $response = $this->actingAs($anon)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('href="'.route('register').'"', false)
        ->assertDontSee('href="'.route('favorites.index').'"', false)
        ->assertDontSee('data-test="logout-button"', false)
        ->assertDontSee('Support our work', false);

    foreach (expectedNavigationLinks(registered: false) as $href) {
        $response->assertSee('href="'.$href.'"', false);
    }
})->with(['sidebar', 'header']);

test('both chromes hide the upgrade prompt from pro subscribers', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $user = User::factory()->create(['stripe_id' => 'cus_test_'.uniqid()]);
    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_fake',
    ]);

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('Support our work', false);
})->with(['sidebar', 'header']);

test('the top bar marks build current on the build page and solve current on the solve page', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('crosswords.index'))
        ->assertSeeInOrder(['data-test="header-nav"', 'href="'.route('crosswords.index').'"', 'data-current'], false);

    $this->actingAs($user)
        ->get(route('crosswords.solving'))
        ->assertSeeInOrder(['data-test="header-nav"', 'href="'.route('crosswords.solving').'"', 'data-current'], false);
});

test('the admin link never uses wire:navigate in either chrome', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $html = $this->actingAs($admin)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->getContent();

    preg_match_all('/<a[^>]*href="'.preg_quote(route('filament.admin.home'), '/').'"[^>]*>/', $html, $matches);

    expect($matches[0])->not->toBeEmpty();

    foreach ($matches[0] as $anchor) {
        expect($anchor)->not->toContain('wire:navigate');
    }
})->with(['sidebar', 'header']);
