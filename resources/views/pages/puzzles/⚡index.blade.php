<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Browse Puzzles')]
#[Layout('layouts.public')]
class extends Component {};
?>

<div
    class="space-y-6"
    x-data="{ showSignup: false }"
    x-on:show-signup-prompt.window="showSignup = true"
>
    <livewire:puzzle-discovery />

    {{-- Signup Prompt Modal --}}
    <template x-teleport="body">
        <div
            x-show="showSignup"
            x-cloak
            x-on:keydown.escape.window="showSignup = false"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            x-on:click.self="showSignup = false"
        >
            <div class="bg-elevated mx-4 w-full max-w-md rounded-2xl p-8 text-center shadow-xl" x-on:click.stop>
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-8 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>
                        <path d="m9 15 2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-fg">{{ __('Ready for more puzzles?') }}</h3>
                <p class="mt-2 text-sm text-fg-muted">
                    {{ __('Create a free account to solve unlimited puzzles, save your progress across devices, and track your stats.') }}
                </p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">
                        {{ __('Create Free Account') }}
                    </a>
                    <a href="{{ route('login') }}" class="border-line-strong rounded-xl border px-6 py-2.5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-700 transition">
                        {{ __('Log In') }}
                    </a>
                </div>
                <button x-on:click="showSignup = false" class="mt-4 text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                    {{ __('Maybe later') }}
                </button>
            </div>
        </div>
    </template>
</div>
