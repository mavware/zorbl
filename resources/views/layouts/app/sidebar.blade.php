<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-page">
@include('partials.impersonation-banner')
<flux:sidebar sticky collapsible="mobile" class="bg-surface border-line border-e">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('crosswords.index') }}" wire:navigate/>
        <flux:sidebar.collapse class="lg:hidden"/>
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group class="grid mb-2">
            <flux:sidebar.item icon="puzzle-piece" :href="route('crosswords.index')"
                               :current="request()->routeIs('crosswords.index') || request()->routeIs('crosswords.editor')"
                               class="font-bold" wire:navigate>
                {{ __('Build') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="play" :href="route('crosswords.solving')"
                               :current="request()->routeIs('crosswords.solving') || request()->routeIs('crosswords.solver')"
                               class="font-bold" wire:navigate>
                {{ __('Solve') }}
            </flux:sidebar.item>
        </flux:sidebar.group>

        <flux:sidebar.group :heading="'Tools'" class="grid border-t-2 mb-2 pt-2">
            <flux:sidebar.item icon="book-open" :href="route('clues.index')"
                               :current="request()->routeIs('clues.index')" wire:navigate>
                {{ __('Clue Library') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="language" :href="route('words.index')" :current="request()->routeIs('words.*')"
                               wire:navigate>
                {{ __('Word Catalog') }}
            </flux:sidebar.item>
        </flux:sidebar.group>

        <flux:sidebar.group class="grid border-t-2 mb-2 pt-2">
            <flux:sidebar.item icon="heart" :href="route('favorites.index')"
                               :current="request()->routeIs('favorites.index')" wire:navigate>
                {{ __('Favorites') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="trophy" :href="route('leaderboard')" :current="request()->routeIs('leaderboard')"
                               wire:navigate>
                {{ __('Leaderboard') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="users" :href="route('constructors.index')"
                               :current="request()->routeIs('constructors.*')" wire:navigate>
                {{ __('Constructors') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="trophy" :href="route('contests.index')" :current="request()->routeIs('contests.*')"
                               wire:navigate>
                {{ __('Contests') }}
            </flux:sidebar.item>
        </flux:sidebar.group>


    </flux:sidebar.nav>

    <flux:spacer/>

    @unless (auth()->user()->isPro())
        <a
            href="{{ route('billing.index') }}"
            wire:navigate
            class="mx-3 mb-2 block rounded-lg border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-amber-500/5 p-3 transition hover:border-amber-500/50 hover:from-amber-500/15 hover:to-amber-500/10"
        >
            <div class="flex items-center gap-2">
                <flux:icon.sparkles class="size-4 text-amber-500"/>
                <span class="text-sm font-semibold text-zinc-100 dark:text-zinc-100">{{ __('Upgrade to Pro') }}</span>
            </div>
            <p class="mt-1 text-xs text-zinc-700 dark:text-zinc-400">
                {{ __('Unlock AI grid fills and clue suggestions.') }}
            </p>
        </a>
    @endunless

    <flux:sidebar.nav>
        @if (auth()->user()->hasRole('Admin'))
            <flux:sidebar.item icon="map" :href="route('roadmap.index')" :current="request()->routeIs('roadmap.index')"
                               wire:navigate>
                {{ __('Roadmap') }}
            </flux:sidebar.item>
        @endif
        <flux:sidebar.item icon="question-mark-circle" :href="route('help.index')"
                           :current="request()->routeIs('help.*')" wire:navigate>
            {{ __('Help Center') }}
        </flux:sidebar.item>

        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('support.index')"
                           :current="request()->routeIs('support.*')" wire:navigate>
            {{ __('Support') }}
        </flux:sidebar.item>

    </flux:sidebar.nav>

    <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name"/>

    <div class="mx-3 mb-3 mt-2 hidden flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 lg:flex dark:text-zinc-600">
        <a href="{{ route('legal.terms') }}" wire:navigate
           class="hover:text-zinc-700 dark:hover:text-zinc-400">{{ __('Terms') }}</a>
        <a href="{{ route('legal.privacy') }}" wire:navigate
           class="hover:text-zinc-700 dark:hover:text-zinc-400">{{ __('Privacy') }}</a>
        <a href="{{ route('legal.cookies') }}" wire:navigate
           class="hover:text-zinc-700 dark:hover:text-zinc-400">{{ __('Cookies') }}</a>
        <a href="{{ route('legal.dmca') }}" wire:navigate
           class="hover:text-zinc-700 dark:hover:text-zinc-400">{{ __('DMCA') }}</a>
    </div>
</flux:sidebar>

<!-- Mobile User Menu -->
<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left"/>

    <flux:spacer/>

    <flux:dropdown position="top" align="end">
        <flux:profile
            :initials="auth()->user()->initials()"
            icon-trailing="chevron-down"
        />

        <flux:menu>
            <flux:menu.radio.group>
                <div class="p-0 text-sm font-normal">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar
                            :name="auth()->user()->name"
                            :initials="auth()->user()->initials()"
                        />

                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>
                </div>
            </flux:menu.radio.group>

            <flux:menu.separator/>

            <flux:menu.radio.group>
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>
                @if (auth()->user()->hasRole('Admin'))
                    <flux:menu.item :href="route('filament.admin.home')" icon="home">
                        {{ __('Admin') }}
                    </flux:menu.item>
                @endif
            </flux:menu.radio.group>

            <flux:menu.separator/>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</flux:header>

{{ $slot }}

@persist('toast')
<flux:toast position="top end"/>
@endpersist

@include('partials.install-prompt')

@fluxScripts
</body>
</html>
