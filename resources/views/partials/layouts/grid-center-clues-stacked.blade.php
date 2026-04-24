{{-- GridCenterCluesStacked: Across above, grid centered, Down below — each
     clue block constrained in width so the grid stays the focal point. --}}
<div class="flex flex-1 flex-col items-center gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
    <div class="hidden w-full max-w-2xl min-h-0 flex-1 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'across'])
    </div>

    @include('partials.editor-grid')

    <div class="hidden w-full max-w-2xl min-h-0 flex-1 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'down'])
    </div>

    @include('partials.editor-mobile-clues')
</div>
