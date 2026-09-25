<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Enums\Theme::current()->value }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-page">
@include('partials.impersonation-banner')
@inject('navigation', 'App\Support\AppNavigation')

{{-- Collapsible on desktop too: Flux shrinks it to a rail of icons, the logo
     mark, and the user's initials, and remembers the choice in localStorage. --}}
<flux:sidebar sticky collapsible class="bg-surface border-line border-e px-0! pt-0!" data-test="app-sidebar">
    <flux:sidebar.header class="p-4 border-b border-line in-data-flux-sidebar-collapsed-desktop:p-2 in-data-flux-sidebar-collapsed-desktop:justify-center">
        <x-app-logo :sidebar="true" href="{{ route('crosswords.index') }}" wire:navigate/>
        <flux:sidebar.collapse data-test="sidebar-collapse-button"/>
    </flux:sidebar.header>

    @include('partials.navigation.sidebar-nav')

    <flux:spacer/>

    @include('partials.navigation.upgrade-callout')

    @include('partials.navigation.sidebar-secondary')

    <div class="hidden px-7 lg:block in-data-flux-sidebar-collapsed-desktop:px-2">
        <flux:separator class="bg-line" />
    </div>

    <div class="hidden px-4 lg:block in-data-flux-sidebar-collapsed-desktop:px-2">
        @if ($navigation->hasAccount(auth()->user()))
            <x-user-menu variant="sidebar" :links="$navigation->sidebarAccount(auth()->user())"/>
        @else
            <div class="grid gap-2">
                @guest
                    <flux:button
                        :href="route('login')"
                        variant="ghost"
                        icon="arrow-right-end-on-rectangle"
                        class="w-full"
                        data-test="sidebar-log-in-button"
                    >
                        {{ __('Log in') }}
                    </flux:button>
                    <flux:button
                        :href="route('register')"
                        variant="ghost"
                        icon="user-plus"
                        class="btn-amber-outline w-full"
                        data-test="sidebar-sign-up-button"
                        wire:navigate
                    >
                        {{ __('Sign up') }}
                    </flux:button>
                @endguest
            </div>
        @endif
    </div>

    @include('partials.navigation.legal-links', ['class' => 'mx-7 mb-3 mt-2 hidden lg:flex in-data-flux-sidebar-collapsed-desktop:hidden!'])
</flux:sidebar>

<!-- Mobile User Menu -->
<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left"/>

    <flux:spacer/>

    @if ($navigation->hasAccount(auth()->user()))
    <x-user-menu variant="header" :links="$navigation->sidebarAccount(auth()->user())"/>
    @else
    @guest
    <flux:button
        :href="route('login')"
        variant="ghost"
        size="sm"
        class="me-1"
        data-test="mobile-log-in-button"
    >
        {{ __('Log in') }}
    </flux:button>
    <flux:button
        :href="route('register')"
        variant="ghost"
        size="sm"
        icon="user-plus"
        class="btn-amber-outline"
        data-test="mobile-sign-up-button"
        wire:navigate
    >
        {{ __('Sign up') }}
    </flux:button>
    @endguest
    @endif
</flux:header>

{{ $slot }}

@persist('toast')
<flux:toast position="top end"/>
@endpersist

{{--@include('partials.install-prompt')--}}

@fluxScripts
</body>
</html>
