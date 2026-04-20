{{-- TabbedCluesBottom: tabbed clue panel below the grid. --}}
<div class="flex flex-1 flex-col gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
    @include('partials.editor-grid')

    <div class="hidden min-h-0 flex-1 flex-col overflow-hidden lg:flex">
        @include('partials.editor-tabbed-clues-desktop')
    </div>

    @include('partials.editor-mobile-clues')
</div>
