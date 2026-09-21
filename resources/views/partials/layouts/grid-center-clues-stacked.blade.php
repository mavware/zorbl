{{-- GridCenterCluesStacked: Across above, grid centered (suggestions pane
     beside it), Down below — each clue block constrained in width so the grid
     stays the focal point. --}}
<div class="flex flex-1 flex-col items-center gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
    <div class="hidden w-full max-w-2xl min-h-0 flex-1 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'across'])
    </div>

    <div class="flex min-h-0 w-full flex-1 justify-center gap-4 overflow-hidden">
        @include('partials.editor-grid')
        @include('partials.editor-suggestions-pane')
    </div>

    <div class="hidden w-full max-w-2xl min-h-0 flex-1 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'down'])
    </div>

    @include('partials.editor-mobile-clues')
</div>
