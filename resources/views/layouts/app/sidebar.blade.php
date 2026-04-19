<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="puzzle-piece" :href="route('crosswords.index')" :current="request()->routeIs('crosswords.index') || request()->routeIs('crosswords.editor')" wire:navigate>
                        {{ __('My Puzzles') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="play" :href="route('crosswords.solving')" :current="request()->routeIs('crosswords.solving') || request()->routeIs('crosswords.solver')" wire:navigate>
                        {{ __('Solving') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="heart" :href="route('favorites.index')" :current="request()->routeIs('favorites.index')" wire:navigate>
                        {{ __('Favorites') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('clues.index')" :current="request()->routeIs('clues.index')" wire:navigate>
                        {{ __('Clue Library') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="language" :href="route('words.index')" :current="request()->routeIs('words.*')" wire:navigate>
                        {{ __('Word Catalog') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()->hasRole('Admin'))
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="map" :href="route('roadmap.index')" :current="request()->routeIs('roadmap.index')" wire:navigate>
                            {{ __('Roadmap') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

            </flux:sidebar.nav>

            <flux:spacer />

            @unless (auth()->user()->isPro())
                <a
                    href="{{ route('billing.index') }}"
                    wire:navigate
                    class="mx-3 mb-2 block rounded-lg border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-amber-500/5 p-3 transition hover:border-amber-500/50 hover:from-amber-500/15 hover:to-amber-500/10"
                >
                    <div class="flex items-center gap-2">
                        <flux:icon.sparkles class="size-4 text-amber-500" />
                        <span class="text-sm font-semibold text-zinc-100 dark:text-zinc-100">{{ __('Upgrade to Pro') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                        {{ __('Unlock AI grid fills and clue suggestions.') }}
                    </p>
                </a>
            @endunless

            <flux:sidebar.nav>

                <flux:sidebar.item icon="chat-bubble-left-right" :href="route('support.index')" :current="request()->routeIs('support.*')" wire:navigate>
                    {{ __('Support') }}
                </flux:sidebar.item>

            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

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

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                        @if (auth()->user()->hasRole('Admin'))
                            <flux:menu.item :href="route('filament.admin.pages.dashboard')" icon="home" wire:navigate>
                                {{ __('Admin') }}
                            </flux:menu.item>
                        @endif
                    </flux:menu.radio.group>

                    <flux:menu.separator />

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
            <flux:toast position="top end" />
        @endpersist

        @fluxScripts
    </body>
</html>
