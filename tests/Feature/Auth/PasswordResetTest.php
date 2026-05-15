<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('reset action rotates remember token and clears all user sessions', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'remember_token' => 'original-token-value',
    ]);
    $stranger = User::factory()->create();

    $row = fn (string $id, int $userId) => [
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => null,
        'user_agent' => null,
        'payload' => '',
        'last_activity' => time(),
    ];
    DB::table('sessions')->insert([
        $row('stale-session-a', $user->id),
        $row('stale-session-b', $user->id),
        $row('stranger-session', $stranger->id),
    ]);

    app(ResetUserPassword::class)->reset($user, [
        'password' => 'fresh-password',
        'password_confirmation' => 'fresh-password',
    ]);

    $user->refresh();
    expect(Hash::check('fresh-password', $user->password))->toBeTrue();
    expect($user->remember_token)->not->toBe('original-token-value');
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', 'stranger-session')->exists())->toBeTrue();
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});
