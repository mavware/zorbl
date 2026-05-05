<?php

use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('webhook settings page can be rendered', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('webhooks.index'))
        ->assertOk()
        ->assertSee('Webhooks');
});

test('webhook settings page requires authentication', function () {
    $this->get(route('webhooks.index'))
        ->assertRedirect(route('login'));
});

test('user can create a webhook endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->set('url', 'https://example.com/webhook')
        ->set('description', 'My test webhook')
        ->set('events', [WebhookEvent::PuzzleCompleted->value])
        ->call('createEndpoint');

    $this->assertDatabaseHas('webhook_endpoints', [
        'user_id' => $user->id,
        'url' => 'https://example.com/webhook',
        'description' => 'My test webhook',
    ]);
});

test('user cannot create endpoint without url', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->set('url', '')
        ->set('events', [WebhookEvent::PuzzleCompleted->value])
        ->call('createEndpoint')
        ->assertHasErrors(['url' => 'required']);
});

test('user cannot create endpoint without events', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->set('url', 'https://example.com/webhook')
        ->set('events', [])
        ->call('createEndpoint')
        ->assertHasErrors(['events' => 'required']);
});

test('user cannot create endpoint with invalid url', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->set('url', 'not-a-url')
        ->set('events', [WebhookEvent::PuzzleCompleted->value])
        ->call('createEndpoint')
        ->assertHasErrors(['url' => 'url']);
});

test('user can toggle webhook endpoint active state', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $endpoint = WebhookEndpoint::factory()->for($user)->create(['is_active' => true]);

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->call('toggleEndpoint', $endpoint->id);

    expect($endpoint->fresh()->is_active)->toBeFalse();
});

test('user can delete webhook endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $endpoint = WebhookEndpoint::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->call('deleteEndpoint', $endpoint->id);

    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
});

test('user cannot toggle another users endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($other)->create(['is_active' => true]);

    expect(fn () => Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->call('toggleEndpoint', $endpoint->id)
    )->toThrow(ModelNotFoundException::class);

    expect($endpoint->fresh()->is_active)->toBeTrue();
});

test('user cannot delete another users endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create();
    $endpoint = WebhookEndpoint::factory()->for($other)->create();

    expect(fn () => Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->call('deleteEndpoint', $endpoint->id)
    )->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('webhook_endpoints', ['id' => $endpoint->id]);
});

test('created endpoint has a generated secret', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->set('url', 'https://example.com/webhook')
        ->set('events', [WebhookEvent::PuzzleCompleted->value])
        ->call('createEndpoint');

    $endpoint = $user->webhookEndpoints()->first();
    expect($endpoint->secret)->not->toBeEmpty()
        ->and(strlen($endpoint->secret))->toBe(32);
});

test('user can view deliveries for their endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $endpoint = WebhookEndpoint::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test('pages::settings.webhooks')
        ->call('viewDeliveries', $endpoint->id)
        ->assertSet('viewingEndpointId', $endpoint->id)
        ->assertSet('showDeliveriesModal', true);
});

test('webhook endpoint model subscribedTo returns correct results', function () {
    $endpoint = WebhookEndpoint::factory()->forEvents([
        WebhookEvent::PuzzleCompleted,
        WebhookEvent::PuzzleLiked,
    ])->create();

    expect($endpoint->subscribedTo(WebhookEvent::PuzzleCompleted))->toBeTrue()
        ->and($endpoint->subscribedTo(WebhookEvent::PuzzleLiked))->toBeTrue()
        ->and($endpoint->subscribedTo(WebhookEvent::PuzzleCommented))->toBeFalse();
});

test('deleting user cascades webhook endpoints', function () {
    $user = User::factory()->create();
    WebhookEndpoint::factory()->for($user)->create();

    $user->delete();

    $this->assertDatabaseMissing('webhook_endpoints', ['user_id' => $user->id]);
});

test('webhook settings page shows nav link in layout', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('webhooks.index'))
        ->assertSee('Webhooks');
});
