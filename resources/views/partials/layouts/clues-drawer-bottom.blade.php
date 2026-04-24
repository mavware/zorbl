{{-- CluesDrawerBottom: slide-up clue drawer anchored to the bottom of the grid. --}}
<div
    class="relative flex flex-1 flex-col overflow-hidden lg:max-h-[calc(100dvh-8rem)]"
    x-data="{ drawerOpen: true }"
>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        @include('partials.editor-grid')
    </div>

    <button
        type="button"
        x-on:click="drawerOpen = !drawerOpen"
        class="absolute right-4 bottom-4 z-30 hidden rounded-md bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white shadow hover:bg-zinc-700 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white lg:inline-flex"
    >
        <span x-text="drawerOpen ? '{{ __('Close Clues') }}' : '{{ __('Show Clues') }}'"></span>
    </button>

    <div
        x-show="drawerOpen"
        x-transition:enter="transition transform ease-in-out duration-200"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition transform ease-in-out duration-200"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        x-cloak
        class="bg-surface border-line absolute inset-x-0 bottom-0 z-20 hidden h-64 flex-row gap-4 border-t p-4 shadow-xl lg:flex"
    >
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>

    @include('partials.editor-mobile-clues')
</div>
