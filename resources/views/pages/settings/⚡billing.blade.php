<?php

use App\Support\AiUsageTracker;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing')] class extends Component {
    public string $billingInterval = 'monthly';

    #[Computed]
    public function user()
    {
        return Auth::user();
    }

    #[Computed]
    public function isPro(): bool
    {
        return $this->user->isPro();
    }

    #[Computed]
    public function subscription()
    {
        return $this->user->subscription('default');
    }

    #[Computed]
    public function onGracePeriod(): bool
    {
        return $this->subscription?->onGracePeriod() ?? false;
    }

    #[Computed]
    public function aiFillsUsed(): int
    {
        return app(AiUsageTracker::class)->monthlyCount($this->user, 'grid_fill');
    }

    #[Computed]
    public function aiFillsRemaining(): int
    {
        return app(AiUsageTracker::class)->remaining($this->user, 'grid_fill');
    }

    #[Computed]
    public function aiCluesUsed(): int
    {
        return app(AiUsageTracker::class)->monthlyCount($this->user, 'clue_generation');
    }

    #[Computed]
    public function aiCluesRemaining(): int
    {
        return app(AiUsageTracker::class)->remaining($this->user, 'clue_generation');
    }

    public function subscribe()
    {
        $priceId = $this->billingInterval === 'yearly'
            ? config('services.stripe.pro_yearly_price')
            : config('services.stripe.pro_monthly_price');

        $checkout = $this->user
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('billing.index') . '?checkout=success',
                'cancel_url' => route('billing.index') . '?checkout=cancelled',
            ]);

        return $this->redirect($checkout->asStripeCheckoutSession()->url);
    }

    public function manageBilling()
    {
        $url = $this->user->billingPortalUrl(route('billing.index'));

        return $this->redirect($url);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Billing')" :subheading="__('Manage your subscription and billing')">
        <div class="my-6 w-full space-y-6">
            {{-- Current Plan --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">
                            @if ($this->isPro)
                                {{ __('Pro Plan') }}
                            @else
                                {{ __('Free Plan') }}
                            @endif
                        </flux:heading>
                        <flux:subheading>
                            @if ($this->isPro && $this->onGracePeriod)
                                {{ __('Your Pro subscription ends on :date.', ['date' => $this->subscription->ends_at->format('M j, Y')]) }}
                            @elseif ($this->isPro)
                                {{ __('You have full access to all Pro features.') }}
                            @else
                                {{ __('Upgrade to Pro for AI tools, unlimited puzzles, and more.') }}
                            @endif
                        </flux:subheading>
                    </div>
                    @if ($this->isPro)
                        <flux:badge color="green" size="lg">{{ __('Pro') }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="lg">{{ __('Free') }}</flux:badge>
                    @endif
                </div>
            </flux:card>

            @if (request()->query('checkout') === 'success')
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>{{ __('Welcome to Pro!') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Your subscription is active. Enjoy AI autofill, unlimited puzzles, and all export formats.') }}</flux:callout.text>
                </flux:callout>
            @endif

            {{-- AI Usage (Pro only) --}}
            @if ($this->isPro)
                <flux:card>
                    <flux:heading size="sm" class="mb-3">{{ __('AI Usage This Month') }}</flux:heading>

                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm">
                                <span>{{ __('AI Autofill') }}</span>
                                <span class="text-zinc-500">{{ $this->aiFillsUsed }} / 50</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-blue-500 transition-all" style="width: {{ min(100, ($this->aiFillsUsed / 50) * 100) }}%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm">
                                <span>{{ __('AI Clue Generation') }}</span>
                                <span class="text-zinc-500">{{ $this->aiCluesUsed }} / 50</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-purple-500 transition-all" style="width: {{ min(100, ($this->aiCluesUsed / 50) * 100) }}%"></div>
                            </div>
                        </div>

                        <flux:text size="xs" class="text-zinc-500">
                            {{ __('Usage resets on the 1st of each month.') }}
                        </flux:text>
                    </div>
                </flux:card>
            @endif

            {{-- Upgrade Section (Free users) --}}
            @unless ($this->isPro)
                <flux:card>
                    <flux:heading size="sm" class="mb-4">{{ __('Upgrade to Pro') }}</flux:heading>

                    <div class="mb-4 space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <flux:icon.check-circle class="size-5 text-green-500" />
                            <span>{{ __('Unlimited puzzle creation') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.check-circle class="size-5 text-green-500" />
                            <span>{{ __('AI Autofill — 50 uses/month') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.check-circle class="size-5 text-green-500" />
                            <span>{{ __('AI Clue Generation — 50 uses/month') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.check-circle class="size-5 text-green-500" />
                            <span>{{ __('All export formats (.puz, .jpz, .pdf)') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.check-circle class="size-5 text-green-500" />
                            <span>{{ __('Unlimited favorite lists') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.check-circle class="size-5 text-green-500" />
                            <span>{{ __('Constructor analytics') }}</span>
                        </div>
                    </div>

                    <div class="mb-4 flex items-center gap-3">
                        <flux:radio.group wire:model="billingInterval" variant="segmented">
                            <flux:radio value="monthly" label="{{ __('Monthly — $5/mo') }}" />
                            <flux:radio value="yearly" label="{{ __('Yearly — $2/mo') }}" />
                        </flux:radio.group>
                    </div>

                    @if ($this->billingInterval === 'yearly')
                        <flux:text size="sm" class="mb-3 text-green-600 dark:text-green-400">
                            {{ __('Save 25% with yearly billing ($72/year)') }}
                        </flux:text>
                    @endif

                    <flux:button wire:click="subscribe" variant="primary">
                        {{ __('Upgrade to Pro') }}
                    </flux:button>
                </flux:card>
            @endunless

            {{-- Manage Subscription (Pro users) --}}
            @if ($this->isPro)
                <flux:card>
                    <flux:heading size="sm" class="mb-2">{{ __('Manage Subscription') }}</flux:heading>
                    <flux:subheading class="mb-4">
                        {{ __('Update your payment method, change plans, or cancel your subscription through the Stripe billing portal.') }}
                    </flux:subheading>
                    <flux:button wire:click="manageBilling" variant="primary">
                        {{ __('Manage Billing') }}
                    </flux:button>
                </flux:card>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
