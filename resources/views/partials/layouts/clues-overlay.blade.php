{{-- CluesOverlay: grid fills, clues appear as a toggleable floating panel. --}}
<div
    class="relative flex flex-1 flex-col overflow-hidden lg:max-h-[calc(100dvh-8rem)]"
    x-data="{ cluesOverlayOpen: true }"
>
    @include('partials.editor-grid')

    <button
        type="button"
        x-on:click="cluesOverlayOpen = !cluesOverlayOpen"
        class="absolute right-4 bottom-4 z-10 hidden rounded-full bg-zinc-800 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-zinc-700 dark:bg-zinc-200 dark:text-zinc-900 dark:hover:bg-white lg:inline-flex"
    >
        <span x-text="cluesOverlayOpen ? '{{ __('Hide Clues') }}' : '{{ __('Show Clues') }}'"></span>
    </button>

    <div
        x-show="cluesOverlayOpen"
        x-transition
        x-cloak
        class="absolute inset-x-4 bottom-20 top-4 z-20 hidden flex-col gap-4 rounded-lg border border-zinc-200 bg-white/95 p-4 shadow-xl backdrop-blur lg:flex lg:flex-row dark:border-zinc-700 dark:bg-zinc-900/95"
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
