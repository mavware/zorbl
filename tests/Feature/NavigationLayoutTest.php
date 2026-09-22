<?php

use App\Enums\Navigation;
use App\Models\HelpArticle;
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
        $registered ? route('constructors.index') : null,
        route('clues.index'),
        route('words.index'),
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
        ->assertDontSee('data-test="sidebar-menu-button"', false);
});

test('the top bar offers a registered user favorites, constructors, help, and support from the user menu', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertDontSee('data-test="header-help-menu"', false)
        ->assertSeeInOrder([
            'data-test="user-menu-links"',
            'href="'.route('favorites.index').'"',
            'href="'.route('constructors.index').'"',
            'href="'.route('help.index').'"',
            'href="'.route('support.index').'"',
            'href="'.route('profile.edit').'"',
        ], false)
        ->getContent();

    preg_match('/data-test="header-nav".*?<\/nav>/s', $html, $headerNav);

    expect($headerNav[0])
        ->toContain('href="'.route('crosswords.index').'"')
        ->not->toContain('href="'.route('favorites.index').'"')
        ->not->toContain('href="'.route('constructors.index').'"')
        ->not->toContain('href="'.route('help.index').'"')
        ->not->toContain('href="'.route('support.index').'"');
});

test('the top bar offers an admin the admin link from the user menu', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-test="user-menu-links"',
            'href="'.route('support.index').'"',
            'href="'.route('filament.admin.home').'"',
            'href="'.route('profile.edit').'"',
        ], false);
});

test('the top bar keeps the help menu for guests, who have no user menu', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSee('data-test="header-help-menu"', false)
        ->assertDontSee('data-test="user-menu-links"', false);
});

test('the sidebar chrome offers a registered user favorites and constructors from the user menu only', function () {
    config(['crosswordbuilder.navigation' => 'sidebar']);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->assertSeeInOrder([
            'data-test="sidebar-menu-button"',
            'data-test="user-menu-links"',
            'href="'.route('favorites.index').'"',
            'href="'.route('constructors.index').'"',
            'href="'.route('profile.edit').'"',
        ], false)
        ->getContent();

    preg_match_all('/data-test="user-menu-links".*?<\/div>/s', $html, $userMenus);

    foreach ($userMenus[0] as $userMenu) {
        expect($userMenu)
            ->toContain('href="'.route('favorites.index').'"')
            ->toContain('href="'.route('constructors.index').'"')
            ->not->toContain('href="'.route('help.index').'"')
            ->not->toContain('href="'.route('support.index').'"');
    }

    preg_match_all('/data-test="user-menu-links".*?href="'.preg_quote(route('profile.edit'), '/').'"/s', $html, $favoritesToSettings);

    expect($favoritesToSettings[0])->toHaveCount(2)
        ->each->not->toContain('data-flux-separator');

    preg_match_all('/<nav[^>]*>.*?<\/nav>/s', $html, $navs);

    foreach ($navs[0] as $nav) {
        expect($nav)
            ->not->toContain('href="'.route('favorites.index').'"')
            ->not->toContain('href="'.route('constructors.index').'"');
    }
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

test('both chromes render the help center for a signed-out visitor with log in and sign up links', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $article = HelpArticle::factory()->create(['title' => 'Public help article']);

    foreach ([route('help.index'), route('help.show', $article)] as $url) {
        $response = $this->get($url)
            ->assertOk()
            ->assertSee('Public help article')
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertDontSee('data-test="logout-button"', false)
            ->assertDontSee('data-test="user-menu-links"', false)
            ->assertDontSee('Support our work', false);

        foreach (expectedNavigationLinks(registered: false) as $href) {
            $response->assertSee('href="'.$href.'"', false);
        }
    }
})->with(['sidebar', 'header']);

test('the sidebar chrome shows a registered user their user menu on the help center', function () {
    config(['crosswordbuilder.navigation' => 'sidebar']);

    $this->actingAs(User::factory()->create())
        ->get(route('help.index'))
        ->assertOk()
        ->assertSee('data-test="sidebar-menu-button"', false)
        ->assertDontSee('data-test="sidebar-log-in-button"', false)
        ->assertDontSee('data-test="header-nav"', false);
});

test('the top bar chrome shows a registered user their user menu on the help center', function () {
    config(['crosswordbuilder.navigation' => 'header']);

    $this->actingAs(User::factory()->create())
        ->get(route('help.index'))
        ->assertOk()
        ->assertSee('data-test="header-nav"', false)
        ->assertSee('data-test="header-menu-button"', false)
        ->assertDontSee('data-test="header-log-in-button"', false)
        ->assertDontSee('data-test="header-help-menu"', false);
});

test('a guest builder sees sign up but not log in on the help center', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $anon = app(AnonymousUserManager::class)->create();

    $this->actingAs($anon)
        ->get(route('help.index'))
        ->assertOk()
        ->assertSee('href="'.route('register').'"', false)
        ->assertDontSee('data-test="sidebar-log-in-button"', false)
        ->assertDontSee('data-test="header-log-in-button"', false)
        ->assertDontSee('data-test="mobile-log-in-button"', false);
})->with(['sidebar', 'header']);

test('the help center marks itself current in the chrome', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $html = $this->get(route('help.index'))->assertOk()->getContent();

    preg_match_all('/<a[^>]*href="'.preg_quote(route('help.index'), '/').'"[^>]*>/', $html, $matches);

    expect($matches[0])->not->toBeEmpty()
        ->and(array_filter($matches[0], fn (string $anchor): bool => str_contains($anchor, 'data-current')))->not->toBeEmpty();
})->with(['sidebar', 'header']);

test('both chromes center the main area under a max width', function (string $navigation) {
    config(['crosswordbuilder.navigation' => $navigation]);

    $html = $this->actingAs(User::factory()->create())
        ->get(route('crosswords.index'))
        ->assertOk()
        ->getContent();

    preg_match('/<div[^>]*data-flux-main[^>]*>/', $html, $main);

    expect($main[0])
        ->toContain('mx-auto')
        ->toContain('max-w-6xl');
})->with(['sidebar', 'header']);
