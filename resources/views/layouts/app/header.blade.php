<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Enums\Theme::current()->value }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-page">
@include('partials.impersonation-banner')
@inject('navigation', 'App\Support\AppNavigation')

<flux:header sticky class="bg-surface border-line border-b">
    <flux:sidebar.toggle class="lg:hidden me-2" icon="bars-2" inset="left"/>

    <x-app-logo href="{{ route('crosswords.index') }}" wire:navigate class="me-4"/>

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

    <flux:spacer/>

    <div class="me-2 flex items-center gap-1 max-lg:hidden">
        @if ($navigation->showsUpgradeCallout(auth()->user()))
            <flux:tooltip :content="__('Help keep Crossword Builder free, and unlock AI grid fills and clue suggestions.')" position="bottom">
                <flux:button
                    :href="route('billing.index')"
                    variant="ghost"
                    size="sm"
                    icon="sparkles"
                    class="btn-amber-outline me-2"
                    data-test="header-upgrade-button"
                    wire:navigate
                >
                    {{ __('Support our work') }}
                </flux:button>
            </flux:tooltip>
        @endif

        {{-- Guests and signed-out visitors have no user menu, so help and support
             stay in the bar for them. Signed-in users find them in the user menu. --}}
        @unless ($navigation->hasAccount(auth()->user()))
            @php
                $secondary = $navigation->secondary(auth()->user());
                $secondaryCurrent = collect($secondary)->contains(fn ($item) => $item->current);
            @endphp

            <flux:dropdown position="bottom" align="end">
                <flux:navbar.item icon="question-mark-circle" icon:trailing="chevron-down" :current="$secondaryCurrent" data-test="header-help-menu">
                    {{ __('Help') }}
                </flux:navbar.item>

                <flux:menu>
                    @foreach ($secondary as $item)
                        <flux:menu.item :icon="$item->icon" :href="$item->href" :attributes="$item->attributes()">
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
                class="me-2"
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

<!-- Mobile Menu -->
<flux:sidebar collapsible="mobile" sticky class="bg-surface border-line lg:hidden border-e px-0! pt-0!">
    <flux:sidebar.header class="p-4 border-b border-line">
        <x-app-logo :sidebar="true" href="{{ route('crosswords.index') }}" wire:navigate/>
        <flux:sidebar.collapse class="lg:hidden"/>
    </flux:sidebar.header>

    @include('partials.navigation.sidebar-nav')

    <flux:spacer/>

    @include('partials.navigation.upgrade-callout')

    @include('partials.navigation.sidebar-secondary')

    @include('partials.navigation.legal-links', ['class' => 'mx-7 mb-3 mt-2 flex'])
</flux:sidebar>

{{ $slot }}

<flux:footer class="border-line border-t py-4! max-lg:hidden">
    @include('partials.navigation.legal-links', ['class' => 'flex'])
</flux:footer>

@persist('toast')
<flux:toast position="top end"/>
@endpersist

@include('partials.install-prompt')

@fluxScripts
</body>
</html>
