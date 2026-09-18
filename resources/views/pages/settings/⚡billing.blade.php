<?php

use App\Support\SupporterGoal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Billing')] class extends Component {
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
    public function isSupporter(): bool
    {
        return $this->user->isSupporter();
    }

    #[Computed]
    public function subscription()
    {
        return $this->user->subscription('default');
    }

    #[Computed]
    public function goal(): SupporterGoal
    {
        return app(SupporterGoal::class);
    }

    #[Computed]
    public function onGracePeriod(): bool
    {
        return $this->subscription?->onGracePeriod() ?? false;
    }

    public function subscribe()
    {
        $checkout = $this->user
            ->newSubscription('default', config('services.stripe.pro_monthly_price'))
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
            @if (request()->query('checkout') === 'success')
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>{{ __('Thank you for your support!') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Your subscription is active. AI autofill and AI clue generation are now unlocked.') }}</flux:callout.text>
                </flux:callout>
            @endif

            {{-- Subscribe (free users) --}}
            @unless ($this->isPro)
                <flux:card>
                    <flux:heading size="sm" class="mb-2">{{ __('Support our work') }}</flux:heading>

                    <flux:text class="mb-4">
                        {{ __('Crossword Builder is free, and we intend to keep it that way. If you believe professional-grade crossword tools should be available to everyone, please consider supporting what we do. As a thank-you, supporters get:') }}
                    </flux:text>

                    <div class="mb-4 space-y-2 text-sm">
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
                            <span>{{ __('A Supporter badge next to your name') }}</span>
                        </div>
                    </div>

                    <flux:text size="sm" class="mb-4">
                        {{ __('$5 per month. Cancel anytime.') }}
                    </flux:text>

                    <flux:button wire:click="subscribe" variant="primary">
                        {{ __('Support Crossword Builder') }}
                    </flux:button>
                </flux:card>
            @endunless

            {{-- Manage / unsubscribe (subscribers) --}}
            @if ($this->isPro)
                <flux:card>
                    <div class="mb-2 flex items-center gap-2">
                        <flux:heading size="sm">
                            @if ($this->isSupporter)
                                {{ __('Thank you for supporting Crossword Builder') }}
                            @else
                                {{ __('Manage Subscription') }}
                            @endif
                        </flux:heading>
                        <x-supporter-badge :supporter="$this->isSupporter" />
                    </div>

                    @if ($this->isSupporter && $this->onGracePeriod)
                        <flux:text class="mb-4">
                            {{ __('Your support has helped keep Crossword Builder free for everyone. Your subscription ends on :date, and you keep AI autofill and AI clue generation until then. You can resume anytime from the billing portal.', ['date' => $this->subscription->ends_at->format('M j, Y')]) }}
                        </flux:text>
                    @elseif ($this->isSupporter)
                        <flux:text class="mb-4">
                            {{ __('Your $5 a month goes directly toward hosting, AI costs, and keeping every tool here free for everyone. It means a lot. As a thank-you, you have full access to AI autofill and AI clue generation, and a Supporter badge appears next to your name across the site.') }}
                        </flux:text>
                    @endif

                    <flux:subheading class="mb-4">
                        {{ __('Update your payment method or cancel your subscription through the Stripe billing portal.') }}
                    </flux:subheading>
                    <flux:button wire:click="manageBilling" variant="primary">
                        {{ __('Manage Billing') }}
                    </flux:button>
                </flux:card>
            @endif

            {{-- Funding goal --}}
            <flux:card data-supporter-goal>
                <div class="mb-2 flex items-center justify-between gap-4">
                    <flux:heading size="sm">{{ __('Our monthly goal') }}</flux:heading>
                    <span class="text-sm font-medium tabular-nums">
                        {{ __('$:current of $:goal', ['current' => number_format($this->goal->currentDollars()), 'goal' => number_format($this->goal->goalDollars())]) }}
                    </span>
                </div>

                <div class="h-3 w-full overflow-hidden rounded-full bg-page" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $this->goal->goalDollars() }}" aria-valuenow="{{ $this->goal->currentDollars() }}" aria-label="{{ __('Progress toward monthly goal') }}">
                    <div class="h-full rounded-full bg-pink-500 transition-all" style="width: {{ $this->goal->percent() }}%"></div>
                </div>

                <flux:text size="sm" class="mt-3">
                    @if ($this->goal->isReached())
                        {{ __('Goal reached! Thanks to :count supporter(s), Crossword Builder is fully funded this month.', ['count' => number_format($this->goal->supporterCount())]) }}
                    @else
                        {{ __(':percent% of the way there, with help from :count supporter(s). Every subscription brings us $5 closer to covering hosting and AI costs so the site stays free for everyone.', ['percent' => $this->goal->percent(), 'count' => number_format($this->goal->supporterCount())]) }}
                    @endif
                </flux:text>
            </flux:card>
        </div>
    </x-pages::settings.layout>
</section>
