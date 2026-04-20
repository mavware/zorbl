{{-- AcrossRightDownLeft: Down clues on the left, grid center, Across clues on the right. --}}
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
    <div class="hidden w-64 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'down'])
    </div>

    @include('partials.editor-grid')

    <div class="hidden w-64 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'across'])
    </div>

    @include('partials.editor-mobile-clues')
</div>
