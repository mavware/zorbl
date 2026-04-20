{{-- TabbedCluesRight: tabbed clue panel on the right of the grid. --}}
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
    @include('partials.editor-grid')

    <div class="hidden w-72 flex-col overflow-hidden lg:flex">
        @include('partials.editor-tabbed-clues-desktop')
    </div>

    @include('partials.editor-mobile-clues')
</div>
