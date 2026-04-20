{{-- CluesRight: Across above Down in a single column on the right of the grid. --}}
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
    @include('partials.editor-grid')

    <div class="hidden w-64 flex-col gap-4 overflow-hidden lg:flex">
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>

    @include('partials.editor-mobile-clues')
</div>
