@props([
    'puzzle',
    'solved' => false,
])

@php
    $iconName = $solved ? 'check-circle' : 'star';
    $iconClass = $solved ? 'text-emerald-500' : 'text-amber-500';
    $borderClass = $solved
        ? 'border-emerald-200 bg-gradient-to-r from-emerald-50 to-green-50 dark:border-emerald-800/50 dark:from-emerald-950/30 dark:to-green-950/30'
        : 'border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 dark:border-amber-800/50 dark:from-amber-950/30 dark:to-orange-950/30';
@endphp

<div class="relative overflow-hidden rounded-xl border {{ $borderClass }} p-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex shrink-0 justify-center sm:justify-start">
            <x-grid-thumbnail :grid="$puzzle->grid" :width="$puzzle->width" :height="$puzzle->height" :cell-size="5" :max-width="64" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <flux:icon :name="$iconName" class="size-5 {{ $iconClass }}" />
                <flux:heading size="lg">{{ __('Puzzle of the Day') }}</flux:heading>
                <flux:badge size="sm" color="amber">{{ today()->format('M j') }}</flux:badge>
                @if($solved)
                    <flux:badge size="sm" color="green" icon="check-circle">{{ __('Solved') }}</flux:badge>
                @endif
            </div>
            <div class="mt-1">
                <span class="font-medium text-fg">{{ $puzzle->displayTitle() }}</span>
                <flux:text size="sm" class="mt-0.5 text-zinc-600 dark:text-zinc-400">
                    {{ __('by :author', ['author' => $puzzle->user->name ?? __('Unknown')]) }}
                    &middot;
                    {{ $puzzle->width }}&times;{{ $puzzle->height }}
                    @if($puzzle->difficulty_label)
                        &middot;
                        {{ __($puzzle->difficulty_label) }}
                    @endif
                </flux:text>
            </div>
        </div>
        <div class="flex shrink-0 flex-col items-end gap-2">
            @if($solved)
                <flux:button variant="filled" size="sm" wire:click="startSolving({{ $puzzle->id }})" icon="eye">
                    {{ __('View Solution') }}
                </flux:button>
            @else
                <flux:button variant="primary" size="sm" wire:click="startSolving({{ $puzzle->id }})" icon="play">
                    {{ __('Solve Now') }}
                </flux:button>
            @endif
            <a href="{{ route('puzzles.daily-history') }}" wire:navigate class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                {{ __('View past puzzles') }} &rarr;
            </a>
        </div>
    </div>
</div>
