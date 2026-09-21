{{-- CluesLeftSideBySide: Across and Down each get their own column on the
     left of the grid — no stacking, no tabs. Suggestions pane on the far left. --}}
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
    @include('partials.editor-suggestions-pane')

    <div class="hidden w-56 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'across'])
    </div>
    <div class="hidden w-56 flex-col overflow-hidden lg:flex">
        @include('partials.editor-clue-panel', ['direction' => 'down'])
    </div>

    @include('partials.editor-grid')

    @include('partials.editor-mobile-clues')
</div>
