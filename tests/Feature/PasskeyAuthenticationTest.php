<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::passkeys());
});

test('login page renders passkey sign-in button', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in with a passkey')
        ->assertSee('passkeyLogin');
});

test('login page includes webauthn autocomplete on email field', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('webauthn', false);
});

test('passkey login options endpoint returns JSON', function () {
    $response = $this->getJson(route('passkey.login-options'));

    $response->assertOk()
        ->assertJsonStructure(['options']);
});

test('security settings page shows passkeys section', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Passkeys');
});

test('passkeys settings shows empty state when user has no passkeys', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.passkeys')
        ->assertSee("You haven't registered any passkeys yet.");
});

test('passkeys settings lists registered passkeys', function () {
    $user = User::factory()->create();

    $user->passkeys()->create([
        'name' => 'My MacBook',
        'credential_id' => 'test-credential-id',
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.passkeys')
        ->assertSee('My MacBook')
        ->assertDontSee("You haven't registered any passkeys yet.");
});

test('passkeys can be deleted from settings', function () {
    $user = User::factory()->create();

    $passkey = $user->passkeys()->create([
        'name' => 'Old Device',
        'credential_id' => 'credential-to-delete',
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.passkeys')
        ->assertSee('Old Device')
        ->call('deletePasskey', $passkey->id)
        ->assertDontSee('Old Device');

    $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
});

test('users cannot delete other users passkeys', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $passkey = $otherUser->passkeys()->create([
        'name' => 'Not My Device',
        'credential_id' => 'other-credential',
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);

    $this->actingAs($user);

    $this->expectException(ModelNotFoundException::class);

    Livewire::test('pages::settings.passkeys')
        ->call('deletePasskey', $passkey->id);

    $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
});

test('passkey registration options endpoint requires authentication', function () {
    $this->getJson(route('passkey.registration-options'))
        ->assertUnauthorized();
});

test('passkey registration options endpoint returns JSON for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->assertJsonStructure(['options']);
});

test('passkey store endpoint requires authentication', function () {
    $this->postJson(route('passkey.store'))
        ->assertUnauthorized();
});

test('passkeys section hidden when feature disabled', function () {
    config(['fortify.features' => [
        Features::registration(),
        Features::resetPasswords(),
    ]]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertDontSee('Passkeys let you sign in');
});

test('user model has passkeys relationship', function () {
    $user = User::factory()->create();

    expect($user->passkeys())->toBeInstanceOf(HasMany::class);
    expect($user->hasPasskeysEnabled())->toBeFalse();
});

test('user with passkeys reports passkeys enabled', function () {
    $user = User::factory()->create();

    $user->passkeys()->create([
        'name' => 'Test Key',
        'credential_id' => 'passkeys-enabled-test',
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);

    expect($user->hasPasskeysEnabled())->toBeTrue();
});

test('passkey delete endpoint requires authentication', function () {
    $user = User::factory()->create();

    $passkey = $user->passkeys()->create([
        'name' => 'Endpoint Test',
        'credential_id' => 'endpoint-delete-test',
        'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
    ]);

    $this->deleteJson(route('passkey.destroy', $passkey))
        ->assertUnauthorized();
});
