<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ \App\Enums\Theme::current()->value }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-page">
@include('partials.impersonation-banner')
<flux:sidebar sticky collapsible="mobile" class="bg-surface border-line border-e px-0! pt-0!">
    <flux:sidebar.header class="p-4 border-b border-line">
        <x-app-logo :sidebar="true" href="{{ route('crosswords.index') }}" wire:navigate/>
        <flux:sidebar.collapse class="lg:hidden"/>
    </flux:sidebar.header>

    @include('partials.navigation.sidebar-nav')

    <flux:spacer/>

    @include('partials.navigation.upgrade-callout')

    @include('partials.navigation.sidebar-secondary')

    <div class="hidden px-7 lg:block">
        <flux:separator class="bg-line" />
    </div>

    <div class="hidden px-4 lg:block">
        @if (auth()->user()->isAnonymous())
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
        @else
            <x-user-menu variant="sidebar"/>
        @endif
    </div>

    @include('partials.navigation.legal-links', ['class' => 'mx-7 mb-3 mt-2 hidden lg:flex'])
</flux:sidebar>

<!-- Mobile User Menu -->
<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left"/>

    <flux:spacer/>

    @if (auth()->user()->isAnonymous())
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
    @else
    <x-user-menu variant="header"/>
    @endif
</flux:header>

{{ $slot }}

@persist('toast')
<flux:toast position="top end"/>
@endpersist

@include('partials.install-prompt')

@fluxScripts
</body>
</html>
