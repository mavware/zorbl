{{-- CluesTop: both clue panels side-by-side above the grid. --}}
<div class="flex flex-1 flex-col gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
    <div class="hidden min-h-0 flex-1 gap-4 overflow-hidden lg:flex">
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>

    @include('partials.editor-grid')

    @include('partials.editor-mobile-clues')
</div>
