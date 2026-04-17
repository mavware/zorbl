<?php

use App\Listeners\HandleStripeWebhook;
use App\Models\StripeWebhookLog;
use App\Models\User;
use App\Notifications\ProSubscriptionStarted;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionPaymentFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Events\WebhookReceived;

function stripePayload(string $type, array $object, ?string $id = null, bool $livemode = false): array
{
    return [
        'id' => $id ?? 'evt_'.uniqid(),
        'type' => $type,
        'livemode' => $livemode,
        'data' => ['object' => $object],
    ];
}

it('auto-registers the HandleStripeWebhook listener on WebhookReceived', function () {
    Event::fake();

    event(new WebhookReceived(stripePayload('customer.updated', ['id' => 'cus_x'])));

    Event::assertListening(WebhookReceived::class, HandleStripeWebhook::class);
});

it('records a log row for every processed event', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_log_'.uniqid()]);

    $payload = stripePayload('customer.subscription.updated', [
        'customer' => $user->stripe_id,
        'status' => 'active',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    $log = StripeWebhookLog::firstWhere('stripe_event_id', $payload['id']);

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->stripe_customer_id)->toBe($user->stripe_id)
        ->and($log->type)->toBe('customer.subscription.updated')
        ->and($log->processed_at)->not->toBeNull();
});

it('is idempotent on duplicate event IDs', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_dup_'.uniqid()]);

    $payload = stripePayload('customer.subscription.created', [
        'customer' => $user->stripe_id,
        'status' => 'active',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));
    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    expect(StripeWebhookLog::where('stripe_event_id', $payload['id'])->count())->toBe(1);
    Notification::assertSentToTimes($user, ProSubscriptionStarted::class, 1);
});

it('notifies the user on customer.subscription.created when active', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_new_'.uniqid()]);

    $payload = stripePayload('customer.subscription.created', [
        'customer' => $user->stripe_id,
        'status' => 'active',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo($user, ProSubscriptionStarted::class);
});

it('skips the welcome notification when subscription is incomplete', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_inc_'.uniqid()]);

    $payload = stripePayload('customer.subscription.created', [
        'customer' => $user->stripe_id,
        'status' => 'incomplete',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertNothingSentTo($user);
});

it('notifies the user on customer.subscription.deleted', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_del_'.uniqid()]);

    $payload = stripePayload('customer.subscription.deleted', [
        'customer' => $user->stripe_id,
        'status' => 'canceled',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo($user, SubscriptionEnded::class);
});

it('notifies the user on invoice.payment_failed with the hosted invoice url', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_fail_'.uniqid()]);

    $payload = stripePayload('invoice.payment_failed', [
        'customer' => $user->stripe_id,
        'billing_reason' => 'subscription_cycle',
        'hosted_invoice_url' => 'https://pay.stripe.com/invoice/abc',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo(
        $user,
        SubscriptionPaymentFailed::class,
        fn (SubscriptionPaymentFailed $n): bool => $n->hostedInvoiceUrl === 'https://pay.stripe.com/invoice/abc',
    );
});

it('skips dunning email on first subscription invoice failure (checkout in progress)', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_firstfail_'.uniqid()]);

    $payload = stripePayload('invoice.payment_failed', [
        'customer' => $user->stripe_id,
        'billing_reason' => 'subscription_create',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertNothingSentTo($user);
});

it('logs unhandled event types without sending any notification', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_unh_'.uniqid()]);

    $payload = stripePayload('product.created', [
        'id' => 'prod_x',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    expect(StripeWebhookLog::where('stripe_event_id', $payload['id'])->exists())->toBeTrue();
    Notification::assertNothingSentTo($user);
});

it('still logs events for unknown Stripe customers', function () {
    Notification::fake();

    $payload = stripePayload('customer.subscription.created', [
        'customer' => 'cus_ghost_'.uniqid(),
        'status' => 'active',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    $log = StripeWebhookLog::firstWhere('stripe_event_id', $payload['id']);

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->processed_at)->not->toBeNull();
});
