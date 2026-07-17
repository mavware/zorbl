<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <meta name="theme-color" content="#0a0a0a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @stack('head_meta')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="bg-page min-h-screen antialiased">
        @include('partials.impersonation-banner')
        {{-- Navigation --}}
        <nav class="bg-elevated border-line sticky top-0 z-50 border-b backdrop-blur-lg">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-6">
                <div class="flex items-center gap-6">
                    <a href="{{ url('/') }}" class="text-xl font-bold tracking-tight text-amber-500">{{ config('app.name') }}</a>
                    <a href="{{ route('puzzles.index') }}" wire:navigate class="text-fg-muted text-sm hover:text-zinc-900 dark:hover:text-zinc-100 transition">
                        {{ __('Browse Puzzles') }}
                    </a>
                    <a href="{{ route('words.index') }}" wire:navigate class="text-fg-muted hidden text-sm transition hover:text-zinc-900 sm:inline dark:hover:text-zinc-100">
                        {{ __('Word Catalog') }}
                    </a>
                    <a href="{{ route('clues.index') }}" wire:navigate class="text-fg-muted hidden text-sm transition hover:text-zinc-900 sm:inline dark:hover:text-zinc-100">
                        {{ __('Clue Library') }}
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('crosswords.index') }}" wire:navigate class="text-fg-muted text-sm hover:text-zinc-900 dark:hover:text-zinc-100 transition">{{ __('Build') }}</a>
                        <a href="{{ route('crosswords.solving') }}" wire:navigate class="text-fg-muted text-sm hover:text-zinc-900 dark:hover:text-zinc-100 transition">{{ __('My Solving') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-fg-muted text-sm hover:text-zinc-900 dark:hover:text-zinc-100 transition">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">{{ __('Sign up') }}</a>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- Content --}}
        <main class="mx-auto max-w-6xl px-6 py-8">
            {{ $slot }}
        </main>

        <footer class="border-line mt-8 border-t py-6">
            <div class="text-fg-muted mx-auto flex max-w-6xl flex-col items-center gap-2 px-6 text-center text-xs sm:flex-row sm:justify-between">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}.</p>
                <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                    <a href="{{ route('words.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Word Catalog') }}</a>
                    <a href="{{ route('clues.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Clue Library') }}</a>
                    <a href="{{ route('tools.convert') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Converter') }}</a>
                    <a href="{{ route('help.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Help') }}</a>
                    <a href="{{ route('legal.terms') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Terms') }}</a>
                    <a href="{{ route('legal.privacy') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Privacy') }}</a>
                    <a href="{{ route('legal.cookies') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Cookies') }}</a>
                    <a href="{{ route('legal.dmca') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('DMCA') }}</a>
                </div>
            </div>
        </footer>

        @include('partials.install-prompt')

        @fluxScripts
    </body>
</html>
