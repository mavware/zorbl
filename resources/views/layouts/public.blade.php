<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-100 antialiased dark:bg-zinc-800">
        {{-- Navigation --}}
        <nav class="sticky top-0 z-50 border-b border-zinc-300 bg-white/80 backdrop-blur-lg dark:border-zinc-700 dark:bg-zinc-800/80">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-6">
                <div class="flex items-center gap-6">
                    <a href="{{ url('/') }}" class="text-xl font-bold tracking-tight text-amber-500">{{ config('app.name') }}</a>
                    <a href="{{ route('puzzles.index') }}" wire:navigate class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition">
                        {{ __('Browse Puzzles') }}
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition">{{ __('Dashboard') }}</a>
                        <a href="{{ route('crosswords.solving') }}" wire:navigate class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition">{{ __('My Solving') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">{{ __('Sign up') }}</a>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- Content --}}
        <main class="mx-auto max-w-6xl px-6 py-8">
            {{ $slot }}
        </main>

        @fluxScripts
    </body>
</html>
