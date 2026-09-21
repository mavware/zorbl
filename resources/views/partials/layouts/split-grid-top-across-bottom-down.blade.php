{{-- SplitGridTopAcrossBottomDown: grid on top (suggestions pane beside it);
     bottom row split into Across (bottom-left) and Down (bottom-right), each
     in its own panel. --}}
<div class="flex flex-1 flex-col gap-4 overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
    <div class="flex min-h-0 flex-1 gap-4 overflow-hidden">
        @include('partials.editor-grid')
        @include('partials.editor-suggestions-pane')
    </div>

    <div class="hidden min-h-0 flex-1 gap-4 overflow-hidden lg:flex">
        <div class="border-line flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border p-2">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div class="border-line flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border p-2">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>

    @include('partials.editor-mobile-clues')
</div>
