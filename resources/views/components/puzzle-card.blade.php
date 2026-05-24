@props([
    'crossword',
    'href' => null,
    'showLike' => false,
    'isLiked' => false,
])

<div
    @if(! $href) wire:key="puzzle-card-{{ $crossword->id }}" @endif
    {{ $attributes->class(['border-line group rounded-xl border p-4 transition-colors transition-transform hover:scale-105 hover:border-zinc-400 dark:hover:border-zinc-500 cursor-pointer']) }}
>
    <div class="mb-3 flex justify-center">
        <x-grid-thumbnail :grid="$crossword->grid" :width="$crossword->width" :height="$crossword->height" />
    </div>

    <flux:heading size="sm" class="truncate text-center">{{ $crossword->displayTitle() }}</flux:heading>
    <flux:text size="sm" class="mt-1 text-center">
        {{ __('by :author', ['author' => $crossword->user->name ?? __('Unknown')]) }}
    </flux:text>

    <div class="mt-1.5 flex flex-wrap items-center justify-center gap-1.5">
        <flux:badge size="sm" variant="outline">{{ __($crossword->puzzleTypeLabel()) }}</flux:badge>
        <flux:badge size="sm" color="indigo">{{ $crossword->width }}&times;{{ $crossword->height }}</flux:badge>
        @if($crossword->difficulty_label)
            <flux:badge
                size="sm"
                :color="match($crossword->difficulty_label) { 'Easy' => 'green', 'Medium' => 'amber', 'Hard' => 'orange', 'Expert' => 'red', default => 'zinc' }"
            >{{ __($crossword->difficulty_label) }}</flux:badge>
        @endif
        @foreach($crossword->tags as $crosswordTag)
            <flux:badge size="sm" color="blue">{{ $crosswordTag->name }}</flux:badge>
        @endforeach
    </div>

    <div class="mt-3 flex items-center justify-between">
        @if($href)
            <flux:button size="sm" variant="primary" :href="$href" wire:navigate>
                @auth
                    {{ __('Start Solving') }}
                @else
                    {{ __('Try This Puzzle') }}
                @endauth
            </flux:button>
        @else
            <flux:button size="sm" variant="primary" wire:click="startSolving({{ $crossword->id }})">
                @auth
                    {{ __('Start Solving') }}
                @else
                    {{ __('Try This Puzzle') }}
                @endauth
            </flux:button>
        @endif

        @if($showLike)
            <button
                wire:click.stop="toggleLike({{ $crossword->id }})"
                class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs transition-colors {{ $isLiked ? 'text-red-500' : 'text-zinc-500 hover:text-red-400' }}"
                @guest title="{{ __('Sign in to like') }}" @endguest
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="{{ $isLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
                <span>{{ $crossword->likes_count }}</span>
            </button>
        @endif
    </div>
</div>
