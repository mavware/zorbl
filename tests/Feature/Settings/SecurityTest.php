<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Two-factor authentication')
        ->assertSee('Enable 2FA');
});

test('security settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Update password')
        ->assertDontSee('Two-factor authentication');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test('pages::settings.security');

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});

test('updating password rotates the remember token and wipes other db sessions', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'remember_token' => 'original-token-value',
    ]);
    $stranger = User::factory()->create();

    DB::table('sessions')->insert([
        sessionRow('user-session-a', $user->id),
        sessionRow('user-session-b', $user->id),
        sessionRow('user-session-c', $user->id),
        sessionRow('stranger-session', $stranger->id),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect($user->refresh()->remember_token)->not->toBe('original-token-value');
    // The current Livewire request's session id is whatever it is — we only
    // assert that at most the single "current" row remains for this user.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBeLessThanOrEqual(1);
    expect(DB::table('sessions')->where('id', 'stranger-session')->exists())->toBeTrue();
});

test('logoutOtherBrowserSessions revokes other sessions and rotates the token', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'remember_token' => 'original-token-value',
    ]);

    DB::table('sessions')->insert([
        sessionRow('user-session-a', $user->id),
        sessionRow('user-session-b', $user->id),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->set('logout_other_password', 'password')
        ->call('logoutOtherBrowserSessions')
        ->assertHasNoErrors()
        ->assertDispatched('other-sessions-revoked');

    expect($user->refresh()->remember_token)->not->toBe('original-token-value');
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBeLessThanOrEqual(1);
});

test('logoutOtherBrowserSessions requires the correct current password', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'remember_token' => 'original-token-value',
    ]);

    DB::table('sessions')->insert([sessionRow('user-session-a', $user->id)]);

    $this->actingAs($user);

    Livewire::test('pages::settings.security')
        ->set('logout_other_password', 'wrong-password')
        ->call('logoutOtherBrowserSessions')
        ->assertHasErrors(['logout_other_password']);

    expect($user->refresh()->remember_token)->toBe('original-token-value');
    expect(DB::table('sessions')->where('id', 'user-session-a')->exists())->toBeTrue();
});

function sessionRow(string $id, int $userId): array
{
    return [
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => null,
        'user_agent' => null,
        'payload' => '',
        'last_activity' => time(),
    ];
}
