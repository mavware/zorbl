<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Throwable;

#[Signature('stripe:check')]
#[Description('Verify Stripe/Cashier configuration, API connectivity, price IDs, and webhook route.')]
class StripeCheck extends Command
{
    public function handle(): int
    {
        $failed = 0;

        $this->line('Checking Stripe integration...');
        $this->newLine();

        $failed += $this->checkKey('cashier.key', 'Publishable key', 'pk_', true);
        $failed += $this->checkKey('cashier.secret', 'Secret key', 'sk_', true);
        $failed += $this->checkKey('cashier.webhook.secret', 'Webhook signing secret', 'whsec_', false);

        $failed += $this->checkApi();

        $failed += $this->checkPriceId('services.stripe.pro_monthly_price', 'Monthly price ID');
        $failed += $this->checkPriceId('services.stripe.pro_yearly_price', 'Yearly price ID');

        $failed += $this->checkWebhookRoute();

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->info('All Stripe integration checks passed.');

        return self::SUCCESS;
    }

    private function checkKey(string $configKey, string $label, string $prefix, bool $hasMode): int
    {
        $value = config($configKey);

        if (empty($value)) {
            $this->line("  [FAIL] {$label}: not set ({$configKey})");

            return 1;
        }

        if (! str_starts_with($value, $prefix)) {
            $this->line("  [WARN] {$label}: value does not start with '{$prefix}'");

            return 0;
        }

        if ($hasMode) {
            $mode = str_starts_with($value, $prefix.'test_') ? 'test' : 'live';
            $this->line("  [OK]   {$label}: set ({$mode} mode)");
        } else {
            $this->line("  [OK]   {$label}: set");
        }

        return 0;
    }

    private function checkApi(): int
    {
        try {
            $client = new StripeClient(config('cashier.secret'));
            $client->balance->retrieve();
        } catch (ApiErrorException $e) {
            $this->line('  [FAIL] Stripe API: '.$e->getMessage());

            return 1;
        } catch (Throwable $e) {
            $this->line('  [FAIL] Stripe API: '.$e->getMessage());

            return 1;
        }

        $this->line('  [OK]   Stripe API: connection successful');

        return 0;
    }

    private function checkPriceId(string $configKey, string $label): int
    {
        $priceId = config($configKey);

        if (empty($priceId)) {
            $this->line("  [FAIL] {$label}: not set ({$configKey})");

            return 1;
        }

        try {
            $client = new StripeClient(config('cashier.secret'));
            $price = $client->prices->retrieve($priceId);
        } catch (ApiErrorException $e) {
            $this->line("  [FAIL] {$label} '{$priceId}': ".$e->getMessage());

            return 1;
        }

        $amount = number_format($price->unit_amount / 100, 2);
        $interval = $price->recurring?->interval ?? 'one-time';
        $status = $price->active ? 'active' : 'INACTIVE';
        $this->line("  [OK]   {$label}: {$priceId} — {$amount} {$price->currency}/{$interval} ({$status})");

        return $price->active ? 0 : 1;
    }

    private function checkWebhookRoute(): int
    {
        $route = Route::getRoutes()->getByName('cashier.webhook');

        if ($route === null) {
            $this->line('  [FAIL] Webhook route: cashier.webhook not registered');

            return 1;
        }

        $this->line("  [OK]   Webhook route: POST /{$route->uri()} registered");

        $events = config('cashier.webhook.events', []);
        $this->line('         Subscribed events: '.count($events));

        return 0;
    }
}
