<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery\MockInterface;

test('google redirect route redirects to google', function () {
    config(['services.google.client_id' => 'test-client-id']);
    config(['services.google.client_secret' => 'test-client-secret']);
    config(['services.google.redirect' => 'http://localhost/auth/google/callback']);

    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
});

test('google callback creates a new user and logs them in', function () {
    $socialiteUser = mockSocialiteUser(
        id: '123456789',
        name: 'Jane Doe',
        email: 'jane@example.com',
    );

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(mockGoogleDriver($socialiteUser));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticated();

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->google_id)->toBe('123456789')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('google callback logs in existing user by google_id', function () {
    $existing = User::factory()->withGoogle('123456789')->create([
        'email' => 'existing@example.com',
    ]);

    $socialiteUser = mockSocialiteUser(
        id: '123456789',
        name: 'Existing User',
        email: 'existing@example.com',
    );

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(mockGoogleDriver($socialiteUser));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticatedAs($existing);
});

test('google callback links google_id to existing user with same email', function () {
    $existing = User::factory()->create([
        'email' => 'existing@example.com',
        'google_id' => null,
    ]);

    $socialiteUser = mockSocialiteUser(
        id: '999888777',
        name: 'Existing User',
        email: 'existing@example.com',
    );

    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(mockGoogleDriver($socialiteUser));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticatedAs($existing);
    expect($existing->refresh()->google_id)->toBe('999888777');
});

test('google callback handles invalid state gracefully', function () {
    Socialite::shouldReceive('driver')
        ->with('google')
        ->andReturn(mockGoogleDriverThatThrows());

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');
    $this->assertGuest();
});

test('authenticated users cannot access google redirect', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('auth.google.redirect'));

    $response->assertRedirect(config('fortify.home'));
});

// --- Helpers ---

function mockSocialiteUser(string $id, string $name, string $email): SocialiteUser
{
    $mock = Mockery::mock(SocialiteUser::class);
    $mock->shouldReceive('getId')->andReturn($id);
    $mock->shouldReceive('getName')->andReturn($name);
    $mock->shouldReceive('getEmail')->andReturn($email);
    $mock->shouldReceive('getAvatar')->andReturn(null);

    return $mock;
}

function mockGoogleDriver(SocialiteUser $user): MockInterface
{
    $driver = Mockery::mock(GoogleProvider::class);
    $driver->shouldReceive('user')->andReturn($user);

    return $driver;
}

function mockGoogleDriverThatThrows(): MockInterface
{
    $driver = Mockery::mock(GoogleProvider::class);
    $driver->shouldReceive('user')
        ->andThrow(new InvalidStateException);

    return $driver;
}
