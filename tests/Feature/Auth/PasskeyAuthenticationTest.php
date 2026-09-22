<?php

use App\Http\Responses\PasskeyLoginResponse;
use App\Models\Crossword;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
});

/**
 * @return array{name: string, credential_id: string, credential: array<string, string>}
 */
function passkeyAttributes(string $name, string $credentialId): array
{
    return [
        'name' => $name,
        'credential_id' => $credentialId,
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ];
}

test('login page offers passkey sign-in and webauthn autofill', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in with a passkey')
        ->assertSee('x-data="passkeyLogin"', false)
        ->assertSee('autocomplete="email webauthn"', false);
});

test('login page hides passkey sign-in when the feature is disabled', function () {
    config(['fortify.features' => [Features::registration()]]);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Sign in with a passkey')
        ->assertSee('autocomplete="email"', false);
});

test('passkey login options are available to guests', function () {
    $this->getJson(route('passkey.login-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge', 'rpId']]);
});

test('passkey login options are not available to signed-in users', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('passkey.login-options'))
        ->assertRedirect();
});

test('passkey login rejects a malformed credential', function () {
    $this->getJson(route('passkey.login-options'))->assertOk();

    $this->postJson(route('passkey.login'), ['credential' => ['id' => 'nope']])
        ->assertUnprocessable();

    $this->assertGuest();
});

test('passkey login response merges guest puzzles into the signed-in account', function () {
    $real = User::factory()->create();
    $anon = app(AnonymousUserManager::class)->create();
    $crossword = Crossword::factory()->for($anon)->create();

    $request = Request::create('/passkeys/login', 'POST', cookies: [
        AnonymousUserManager::COOKIE_NAME => $anon->anonymous_token,
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->setUserResolver(fn () => $real);

    $response = app(PasskeyLoginResponseContract::class)->toResponse($request);

    expect(app(PasskeyLoginResponseContract::class))->toBeInstanceOf(PasskeyLoginResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode((string) $response->getContent(), true))->toHaveKey('redirect');
    expect($crossword->fresh()->user_id)->toBe($real->id);
    expect(User::find($anon->id))->toBeNull();
});

test('passkey login response redirects standard requests', function () {
    $request = Request::create('/passkeys/login', 'POST');
    $request->setUserResolver(fn () => User::factory()->create());

    $response = app(PasskeyLoginResponseContract::class)->toResponse($request);

    expect($response->getStatusCode())->toBe(302);
});

test('security settings page shows the passkeys section', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Passkeys')
        ->assertSee('Add passkey');
});

test('security settings page hides the passkeys section when the feature is disabled', function () {
    config(['fortify.features' => [Features::registration()]]);

    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertDontSee('Add passkey');
});

test('passkeys section shows an empty state', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.passkeys')
        ->assertSee("You haven't added any passkeys yet.");
});

test('passkeys section lists registered passkeys', function () {
    $user = User::factory()->create();
    $user->passkeys()->create(passkeyAttributes('My MacBook', 'cred-macbook'));

    $this->actingAs($user);

    Livewire::test('pages::settings.passkeys')
        ->assertSee('My MacBook')
        ->assertDontSee("You haven't added any passkeys yet.");
});

test('passkeys can be removed from the settings page', function () {
    $user = User::factory()->create();
    $passkey = $user->passkeys()->create(passkeyAttributes('Old Device', 'cred-old'));

    $this->actingAs($user);

    Livewire::test('pages::settings.passkeys')
        ->assertSee('Old Device')
        ->call('deletePasskey', $passkey->id)
        ->assertDontSee('Old Device');

    $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
});

test('users cannot remove another user\'s passkey', function () {
    $user = User::factory()->create();
    $passkey = User::factory()->create()->passkeys()->create(passkeyAttributes('Not Mine', 'cred-other'));

    $this->actingAs($user);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('pages::settings.passkeys')
        ->call('deletePasskey', $passkey->id);
});

test('passkeys section refreshes after the browser registers a passkey', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.passkeys')
        ->assertDontSee('Fresh Key');

    $user->passkeys()->create(passkeyAttributes('Fresh Key', 'cred-fresh'));

    $component->dispatch('passkey-registered')
        ->assertSee('Fresh Key');
});

test('passkey registration options require authentication', function () {
    $this->getJson(route('passkey.registration-options'))
        ->assertUnauthorized();
});

test('passkey registration options require a confirmed password', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('passkey.registration-options'))
        ->assertRedirect(route('password.confirm'));
});

test('passkey registration options are returned once the password is confirmed', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge', 'user' => ['id', 'name', 'displayName']]]);
});

test('passkey store and delete endpoints require authentication', function () {
    $passkey = User::factory()->create()->passkeys()->create(passkeyAttributes('Endpoint Test', 'cred-endpoint'));

    $this->postJson(route('passkey.store'))->assertUnauthorized();
    $this->deleteJson(route('passkey.destroy', $passkey))->assertUnauthorized();

    $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
});

test('passkeys are removed when their owner is deleted', function () {
    $user = User::factory()->create();
    $passkey = $user->passkeys()->create(passkeyAttributes('Cascade', 'cred-cascade'));

    $user->delete();

    $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
});

test('user model exposes passkeys', function () {
    $user = User::factory()->create();

    expect($user->passkeys())->toBeInstanceOf(HasMany::class);
    expect($user->hasPasskeysEnabled())->toBeFalse();

    $user->passkeys()->create(passkeyAttributes('Test Key', 'cred-enabled'));

    expect($user->fresh()->hasPasskeysEnabled())->toBeTrue();
});

test('confirm password page offers passkey confirmation only to users with a passkey', function () {
    $withoutPasskey = User::factory()->create();
    $withPasskey = User::factory()->create();
    $withPasskey->passkeys()->create(passkeyAttributes('Confirm Key', 'cred-confirm'));

    $this->actingAs($withoutPasskey)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertDontSee('Confirm with a passkey');

    $this->actingAs($withPasskey)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertSee('Confirm with a passkey');
});
