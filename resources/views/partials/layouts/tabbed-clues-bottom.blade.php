{{-- TabbedCluesBottom: tabbed clue panel below the grid; suggestions pane
     beside the grid. --}}
<div class="flex flex-1 flex-col gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
    <div class="flex min-h-0 flex-1 gap-4 overflow-hidden">
        @include('partials.editor-grid')
        @include('partials.editor-suggestions-pane')
    </div>

    <div class="hidden min-h-0 flex-1 flex-col overflow-hidden lg:flex">
        @include('partials.editor-tabbed-clues-desktop')
    </div>

    @include('partials.editor-mobile-clues')
</div>
