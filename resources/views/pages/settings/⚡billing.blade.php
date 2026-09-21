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
                <div class="border-amber-400/60 flex items-start gap-3.5 rounded-sm border p-[18px]">
                    <div class="border-amber-400 flex size-9 shrink-0 items-center justify-center rounded-sm border text-amber-400">
                        <flux:icon name="check-circle" class="size-4" />
                    </div>
                    <div>
                        <div class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ __('Thank you for your support!') }}</div>
                        <p class="text-ink-muted mt-1.5 text-sm leading-[1.65]">{{ __('Your subscription is active. AI autofill and AI clue generation are now unlocked.') }}</p>
                    </div>
                </div>
            @endif

            {{-- Subscribe (free users) --}}
            @unless ($this->isPro)
                <div class="border-border rounded-sm border p-[18px]">
                    <h3 class="font-classical text-ink mb-2 text-[19px] leading-tight font-semibold">{{ __('Support our work') }}</h3>

                    <p class="text-ink-muted mb-4 text-sm leading-[1.65]">
                        {{ __('Crossword Builder is free, and we intend to keep it that way. If you believe professional-grade crossword tools should be available to everyone, please consider supporting what we do. As a thank-you, supporters get:') }}
                    </p>

                    <div class="text-ink mb-4 space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" class="size-5 shrink-0 text-amber-400" />
                            <span>{{ __('AI Autofill — 50 uses/month') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" class="size-5 shrink-0 text-amber-400" />
                            <span>{{ __('AI Clue Generation — 50 uses/month') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon name="check-circle" class="size-5 shrink-0 text-amber-400" />
                            <span>{{ __('A Supporter badge next to your name') }}</span>
                        </div>
                    </div>

                    <p class="meta-classical mb-4 normal-case tracking-normal">
                        {{ __('$5 per month. Cancel anytime.') }}
                    </p>

                    <button type="button" class="btn-classical btn-amber-outline" wire:click="subscribe">
                        {{ __('Support Crossword Builder') }}
                    </button>
                </div>
            @endunless

            {{-- Manage / unsubscribe (subscribers) --}}
            @if ($this->isPro)
                <div class="border-border rounded-sm border p-[18px]">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="font-classical text-ink text-[19px] leading-tight font-semibold">
                            @if ($this->isSupporter)
                                {{ __('Thank you for supporting Crossword Builder') }}
                            @else
                                {{ __('Manage Subscription') }}
                            @endif
                        </h3>
                        <x-supporter-badge :supporter="$this->isSupporter" />
                    </div>

                    @if ($this->isSupporter && $this->onGracePeriod)
                        <p class="text-ink-muted mb-4 text-sm leading-[1.65]">
                            {{ __('Your support has helped keep Crossword Builder free for everyone. Your subscription ends on :date, and you keep AI autofill and AI clue generation until then. You can resume anytime from the billing portal.', ['date' => $this->subscription->ends_at->format('M j, Y')]) }}
                        </p>
                    @elseif ($this->isSupporter)
                        <p class="text-ink-muted mb-4 text-sm leading-[1.65]">
                            {{ __('Your $5 a month goes directly toward hosting, AI costs, and keeping every tool here free for everyone. It means a lot. As a thank-you, you have full access to AI autofill and AI clue generation, and a Supporter badge appears next to your name across the site.') }}
                        </p>
                    @endif

                    <p class="text-ink-muted mb-4 text-sm leading-[1.65]">
                        {{ __('Update your payment method or cancel your subscription through the Stripe billing portal.') }}
                    </p>
                    <button type="button" class="btn-classical btn-amber-outline" wire:click="manageBilling">
                        {{ __('Manage Billing') }}
                    </button>
                </div>
            @endif

            {{-- Funding goal --}}
            <div class="border-border rounded-sm border p-[18px]" data-supporter-goal>
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h3 class="font-classical text-ink text-[19px] leading-tight font-semibold">{{ __('Our monthly goal') }}</h3>
                    <span class="font-classical text-ink tnum text-[16px] font-medium">
                        {{ __('$:current of $:goal', ['current' => number_format($this->goal->currentDollars()), 'goal' => number_format($this->goal->goalDollars())]) }}
                    </span>
                </div>

                <div class="bg-border h-0.5 w-full overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $this->goal->goalDollars() }}" aria-valuenow="{{ $this->goal->currentDollars() }}" aria-label="{{ __('Progress toward monthly goal') }}">
                    <div class="h-full bg-amber-400 transition-all" style="width: {{ $this->goal->percent() }}%"></div>
                </div>

                <p class="text-ink-muted mt-3 text-sm leading-[1.65]">
                    @if ($this->goal->isReached())
                        {{ __('Goal reached! Thanks to :count supporter(s), Crossword Builder is fully funded this month.', ['count' => number_format($this->goal->supporterCount())]) }}
                    @else
                        {{ __(':percent% of the way there, with help from :count supporter(s). Every subscription brings us $5 closer to covering hosting and AI costs so the site stays free for everyone.', ['percent' => $this->goal->percent(), 'count' => number_format($this->goal->supporterCount())]) }}
                    @endif
                </p>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
