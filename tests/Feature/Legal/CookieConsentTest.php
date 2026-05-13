<?php

use App\Models\CookieConsent;
use App\Models\User;
use App\Support\CookieConsentManager;

test('banner shows for visitors from regulated regions (UK)', function () {
    $response = $this->withHeaders(['CF-IPCountry' => 'GB'])
        ->get(route('puzzles.index'));

    $response->assertOk()->assertSee('We use cookies');
});

test('banner is hidden for visitors from unregulated regions (US)', function () {
    $response = $this->withHeaders(['CF-IPCountry' => 'US'])
        ->get(route('puzzles.index'));

    $response->assertOk()->assertDontSee('We use cookies');
});

test('banner is shown when no country header is present (fail-safe)', function () {
    $response = $this->get(route('puzzles.index'));

    $response->assertOk()->assertSee('We use cookies');
});

test('store endpoint records anonymous consent against an ip+ua hash', function () {
    $this->withHeaders(['CF-IPCountry' => 'DE'])
        ->postJson(route('cookie-consent.store'), [
            'choice' => CookieConsent::CHOICE_ACCEPT_ALL,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    $consent = CookieConsent::query()->first();

    expect($consent)->not->toBeNull()
        ->and($consent->user_id)->toBeNull()
        ->and($consent->identifier_hash)->not->toBeNull()
        ->and($consent->choice)->toBe(CookieConsent::CHOICE_ACCEPT_ALL)
        ->and($consent->version)->toBe(CookieConsentManager::VERSION)
        ->and($consent->region_country)->toBe('DE');
});

test('store endpoint records authenticated consent against the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeaders(['CF-IPCountry' => 'FR'])
        ->postJson(route('cookie-consent.store'), [
            'choice' => CookieConsent::CHOICE_REJECT_NON_ESSENTIAL,
        ])
        ->assertOk();

    $consent = CookieConsent::query()->where('user_id', $user->id)->first();

    expect($consent)->not->toBeNull()
        ->and($consent->choice)->toBe(CookieConsent::CHOICE_REJECT_NON_ESSENTIAL)
        ->and($consent->region_country)->toBe('FR');
});

test('store endpoint validates choice', function () {
    $this->postJson(route('cookie-consent.store'), ['choice' => 'bogus'])
        ->assertUnprocessable();
});

test('banner is suppressed for authenticated users who have already chosen', function () {
    $user = User::factory()->create();

    CookieConsent::create([
        'user_id' => $user->id,
        'choice' => CookieConsent::CHOICE_REJECT_NON_ESSENTIAL,
        'version' => CookieConsentManager::VERSION,
        'region_country' => 'GB',
    ]);

    $this->actingAs($user)
        ->withHeaders(['CF-IPCountry' => 'GB'])
        ->get(route('puzzles.index'))
        ->assertOk()
        ->assertDontSee('We use cookies');
});

test('banner is suppressed when the consent cookie is already set', function () {
    $this->withHeaders(['CF-IPCountry' => 'GB'])
        ->withCookie(CookieConsentManager::COOKIE_NAME, CookieConsent::CHOICE_ACCEPT_ALL)
        ->get(route('puzzles.index'))
        ->assertOk()
        ->assertDontSee('We use cookies');
});

test('repeat consent updates the existing record rather than duplicating', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('cookie-consent.store'), ['choice' => CookieConsent::CHOICE_REJECT_NON_ESSENTIAL])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('cookie-consent.store'), ['choice' => CookieConsent::CHOICE_ACCEPT_ALL])
        ->assertOk();

    expect(CookieConsent::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(CookieConsent::query()->where('user_id', $user->id)->value('choice'))
        ->toBe(CookieConsent::CHOICE_ACCEPT_ALL);
});
