{{-- CluesDrawerLeft: slide-out clue drawer on the left of the grid. --}}
<div
    class="relative flex flex-1 overflow-hidden lg:max-h-[calc(100dvh-8rem)]"
    x-data="{ drawerOpen: true }"
>
    <div
        x-show="drawerOpen"
        x-transition:enter="transition transform ease-in-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in-out duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        x-cloak
        class="bg-surface border-line absolute inset-y-0 left-0 z-20 hidden w-72 flex-col gap-4 border-r p-4 shadow-xl lg:flex"
    >
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>

    <button
        type="button"
        x-on:click="drawerOpen = !drawerOpen"
        class="absolute left-4 top-4 z-30 hidden rounded-md bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white shadow hover:bg-zinc-700 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white lg:inline-flex"
    >
        <span x-text="drawerOpen ? '{{ __('Close Clues') }}' : '{{ __('Show Clues') }}'"></span>
    </button>

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        @include('partials.editor-grid')
        @include('partials.editor-mobile-clues')
    </div>
</div>
