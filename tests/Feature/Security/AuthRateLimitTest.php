<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // Make sure each test starts with empty buckets — RefreshDatabase doesn't
    // clear the cache-backed rate limiter store.
    RateLimiter::clear('register-attempts');
    RateLimiter::clear('password-reset-requests');
    RateLimiter::clear('verification-resend');
    RateLimiter::clear('oauth-callback');
});

test('registration POST is throttled after 10 attempts per IP per minute', function () {
    $payload = [
        'name' => 'Test User',
        'email' => 'test@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    // First 10 should pass through (will fail validation on dup email after #1,
    // but throttling is separate from validation).
    for ($i = 0; $i < 10; $i++) {
        $response = $this->post(route('register.store'), array_merge($payload, [
            'email' => "test{$i}@example.test",
        ]));
        expect($response->status())->not->toBe(429);
    }

    $this->post(route('register.store'), array_merge($payload, ['email' => 'eleventh@example.test']))
        ->assertStatus(429);
});

test('forgot-password POST is throttled after 5 attempts per email+IP per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.email'), ['email' => 'victim@example.test']);
    }

    $this->post(route('password.email'), ['email' => 'victim@example.test'])
        ->assertStatus(429);
});

test('different emails do not share the password-reset bucket', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.email'), ['email' => 'victim-a@example.test']);
    }

    // Hitting the limit for one address shouldn't lock out a request for another.
    $response = $this->post(route('password.email'), ['email' => 'victim-b@example.test']);
    expect($response->status())->not->toBe(429);
});

test('reset-password POST is throttled', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.update'), [
            'token' => 'fake-token',
            'email' => 'reset@example.test',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);
    }

    $this->post(route('password.update'), [
        'token' => 'fake-token',
        'email' => 'reset@example.test',
        'password' => 'newpassword',
        'password_confirmation' => 'newpassword',
    ])->assertStatus(429);
});

test('GET requests on auth routes are NOT throttled by ThrottleAuthRoutes', function () {
    // Visiting the register page repeatedly is not abusive — only POSTs are.
    for ($i = 0; $i < 30; $i++) {
        $this->get(route('register'))->assertOk();
    }
});

test('Google OAuth callback is throttled after 20 hits per IP per minute', function () {
    for ($i = 0; $i < 20; $i++) {
        // We don't care about the response, just that it isn't 429 yet.
        $this->get(route('auth.google.callback'));
    }

    $this->get(route('auth.google.callback'))->assertStatus(429);
});

test('login route keeps its existing per-credential throttle untouched', function () {
    // Create a user so the login form would otherwise validate against them.
    User::factory()->create(['email' => 'real@example.test', 'password' => bcrypt('password')]);

    // The pre-existing Fortify `login` limiter is 5/min keyed by email+IP.
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), ['email' => 'real@example.test', 'password' => 'wrong']);
    }

    $this->post(route('login'), ['email' => 'real@example.test', 'password' => 'wrong'])
        ->assertStatus(429);
});
