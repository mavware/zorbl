<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('issues a token with valid credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'secret-password',
        'device_name' => 'testing',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user',
        ]);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'testing',
    ])->assertUnauthorized();
});

it('validates required fields', function () {
    $this->postJson('/api/v1/tokens', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('revokes the current token', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/v1/tokens')
        ->assertNoContent();
});

it('requires auth to revoke token', function () {
    $this->deleteJson('/api/v1/tokens')
        ->assertUnauthorized();
});
