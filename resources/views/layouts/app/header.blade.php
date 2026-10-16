<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Enums\Theme::current()->value }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-page">
@include('partials.impersonation-banner')
@inject('navigation', 'App\Support\AppNavigation')

{{-- There is no mobile drawer: every screen size gets the logo and the main
     destinations inline. Below lg the items drop their icons and the brand
     drops its name (below sm) so the row fits a phone. --}}
<flux:header sticky class="bg-surface border-line border-b max-lg:px-3">
    <x-app-logo href="{{ route('crosswords.index') }}" wire:navigate class="me-4 max-sm:me-1 max-sm:[&>div:last-child]:hidden"/>

    <flux:navbar class="-mb-px max-lg:hidden" data-test="header-nav">
        @foreach ($navigation->main(auth()->user()) as $group)
            @unless ($loop->first)
                <flux:separator vertical class="mx-1 my-2 bg-line"/>
            @endunless

            @foreach ($group as $item)
                <flux:navbar.item
                    :icon="$item->icon"
                    :href="$item->href"
                    :current="$item->current"
                    :attributes="$item->attributes()"
                >
                    {{ $item->label }}
                </flux:navbar.item>
            @endforeach
        @endforeach
    </flux:navbar>

    <flux:navbar class="-mb-px gap-0 lg:hidden" data-test="header-nav-mobile">
        @foreach (collect($navigation->main(auth()->user()))->flatten() as $item)
            <flux:navbar.item
                :href="$item->href"
                :current="$item->current"
                :attributes="$item->attributes()"
                class="max-sm:px-2!"
            >
                {{ $item->label }}
            </flux:navbar.item>
        @endforeach
    </flux:navbar>

    <flux:spacer/>

    <div class="me-2 flex items-center gap-1 max-lg:me-1">
        @if ($navigation->showsUpgradeCallout(auth()->user()))
            <flux:tooltip :content="__('Help keep Crossword Builder free, and unlock AI grid fills and clue suggestions.')" position="bottom">
                <flux:button
                    :href="route('billing.index')"
                    variant="ghost"
                    size="sm"
                    icon="sparkles"
                    class="btn-amber-outline me-2 max-lg:me-0"
                    :aria-label="__('Support our work')"
                    data-test="header-upgrade-button"
                    wire:navigate
                >
                    <span class="max-lg:hidden">{{ __('Support our work') }}</span>
                </flux:button>
            </flux:tooltip>
        @endif

        {{-- Guests and signed-out visitors have no user menu, so help and support
             stay in the bar for them (icon only below lg). Signed-in users find
             them in the user menu. --}}
        @unless ($navigation->hasAccount(auth()->user()))
            @php
                $secondary = $navigation->secondary(auth()->user());
                $secondaryCurrent = collect($secondary)->contains(fn ($item) => $item->current);
            @endphp

            <flux:dropdown position="bottom" align="end">
                <flux:navbar.item
                    icon="question-mark-circle"
                    icon:trailing="chevron-down"
                    :current="$secondaryCurrent"
                    class="max-lg:[&_[data-content]]:hidden"
                    :aria-label="__('Help')"
                    data-test="header-help-menu"
                >
                    {{ __('Help') }}
                </flux:navbar.item>

                <flux:menu>
                    @foreach ($secondary as $item)
                        <flux:menu.item
                            :icon="$item->icon"
                            :href="$item->href"
                            :attributes="$item->attributes()->merge($item->current ? ['data-current' => true, 'aria-current' => 'page'] : [])"
                        >
                            {{ $item->label }}
                        </flux:menu.item>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endunless
    </div>

    @if ($navigation->hasAccount(auth()->user()))
        <x-user-menu variant="header" :links="$navigation->account(auth()->user())"/>
    @else
        @guest
            <flux:button
                :href="route('login')"
                variant="ghost"
                size="sm"
                class="me-2 max-sm:me-1"
                data-test="header-log-in-button"
            >
                {{ __('Log in') }}
            </flux:button>
            <flux:button
                :href="route('register')"
                variant="ghost"
                size="sm"
                icon="user-plus"
                class="btn-amber-outline"
                data-test="header-sign-up-button"
                wire:navigate
            >
                {{ __('Sign up') }}
            </flux:button>
        @endguest
    @endif
</flux:header>

{{ $slot }}

<flux:footer class="border-line border-t py-4!">
    @include('partials.navigation.legal-links', ['class' => 'flex'])
</flux:footer>

@persist('toast')
<flux:toast position="top end"/>
@endpersist

{{--@include('partials.install-prompt')--}}

@fluxScripts
</body>
</html>
