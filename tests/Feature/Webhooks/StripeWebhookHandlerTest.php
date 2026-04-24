<?php

use App\Listeners\HandleStripeWebhook;
use App\Models\StripeWebhookLog;
use App\Models\User;
use App\Notifications\ProSubscriptionStarted;
use App\Notifications\SubscriptionDisputeAlert;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionPaymentFailed;
use App\Notifications\SubscriptionPlanChanged;
use App\Notifications\SubscriptionRefunded;
use App\Notifications\SubscriptionRenewed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Events\WebhookReceived;
use Spatie\Permission\Models\Role;

function stripePayload(string $type, array $object, ?string $id = null, bool $livemode = false, array $previousAttributes = []): array
{
    $data = ['object' => $object];

    if ($previousAttributes !== []) {
        $data['previous_attributes'] = $previousAttributes;
    }

    return [
        'id' => $id ?? 'evt_'.uniqid(),
        'type' => $type,
        'livemode' => $livemode,
        'data' => $data,
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

it('notifies the user when their plan changes on customer.subscription.updated', function () {
    Notification::fake();
    config()->set('services.stripe.pro_monthly_price', 'price_monthly');
    config()->set('services.stripe.pro_yearly_price', 'price_yearly');
    $user = User::factory()->create(['stripe_id' => 'cus_plan_'.uniqid()]);

    $payload = stripePayload(
        type: 'customer.subscription.updated',
        object: [
            'customer' => $user->stripe_id,
            'status' => 'active',
            'items' => ['data' => [['price' => ['id' => 'price_yearly']]]],
        ],
        previousAttributes: [
            'items' => ['data' => [['price' => ['id' => 'price_monthly']]]],
        ],
    );

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo(
        $user,
        SubscriptionPlanChanged::class,
        fn (SubscriptionPlanChanged $n): bool => $n->fromPlan === 'Pro Monthly' && $n->toPlan === 'Pro Yearly',
    );
});

it('does not send a plan change email when customer.subscription.updated has no price change', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_noplan_'.uniqid()]);

    $payload = stripePayload(
        type: 'customer.subscription.updated',
        object: [
            'customer' => $user->stripe_id,
            'status' => 'active',
            'items' => ['data' => [['price' => ['id' => 'price_monthly']]]],
        ],
        previousAttributes: ['status' => 'incomplete'],
    );

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertNothingSentTo($user);
});

it('notifies the user on invoice.payment_succeeded for a subscription cycle', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_renew_'.uniqid()]);

    $payload = stripePayload('invoice.payment_succeeded', [
        'customer' => $user->stripe_id,
        'billing_reason' => 'subscription_cycle',
        'amount_paid' => 999,
        'currency' => 'usd',
        'hosted_invoice_url' => 'https://pay.stripe.com/invoice/renew',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo(
        $user,
        SubscriptionRenewed::class,
        fn (SubscriptionRenewed $n): bool => $n->amount === 999
            && $n->currency === 'usd'
            && $n->hostedInvoiceUrl === 'https://pay.stripe.com/invoice/renew',
    );
});

it('skips the renewal email for the initial invoice.payment_succeeded', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_firstpay_'.uniqid()]);

    $payload = stripePayload('invoice.payment_succeeded', [
        'customer' => $user->stripe_id,
        'billing_reason' => 'subscription_create',
        'amount_paid' => 999,
        'currency' => 'usd',
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertNothingSentTo($user);
});

it('notifies the user on charge.refunded', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_ref_'.uniqid()]);

    $payload = stripePayload('charge.refunded', [
        'customer' => $user->stripe_id,
        'amount_refunded' => 999,
        'currency' => 'usd',
        'refunded' => true,
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo(
        $user,
        SubscriptionRefunded::class,
        fn (SubscriptionRefunded $n): bool => $n->amountRefunded === 999 && $n->fullRefund === true,
    );
});

it('does not send a refund email when no amount was refunded', function () {
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_ref0_'.uniqid()]);

    $payload = stripePayload('charge.refunded', [
        'customer' => $user->stripe_id,
        'amount_refunded' => 0,
        'currency' => 'usd',
        'refunded' => false,
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertNothingSentTo($user);
});

it('notifies admins on charge.dispute.created', function () {
    Notification::fake();
    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $customer = User::factory()->create(['stripe_id' => 'cus_dispute_'.uniqid()]);

    $payload = stripePayload('charge.dispute.created', [
        'id' => 'dp_123',
        'amount' => 999,
        'currency' => 'usd',
        'reason' => 'fraudulent',
        'charge' => 'ch_123',
        'customer' => $customer->stripe_id,
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertSentTo(
        $admin,
        SubscriptionDisputeAlert::class,
        fn (SubscriptionDisputeAlert $n): bool => $n->disputeId === 'dp_123'
            && $n->amount === 999
            && $n->reason === 'fraudulent'
            && $n->customerUserId === $customer->id
            && $n->customerEmail === $customer->email,
    );
    Notification::assertNothingSentTo($customer);
});

it('sends no dispute alert when no admin users exist', function () {
    Notification::fake();
    $customer = User::factory()->create(['stripe_id' => 'cus_noadmin_'.uniqid()]);

    $payload = stripePayload('charge.dispute.created', [
        'id' => 'dp_none',
        'amount' => 500,
        'currency' => 'usd',
        'reason' => 'duplicate',
        'customer' => $customer->stripe_id,
    ]);

    (new HandleStripeWebhook)->handle(new WebhookReceived($payload));

    Notification::assertNothingSent();
});
