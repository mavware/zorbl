{{-- SplitGridLeftAcrossRightDown: grid on the left; right column split into
     Across (top-right) and Down (bottom-right), each in its own panel. --}}
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
    @include('partials.editor-grid')

    <div class="hidden w-72 flex-col gap-4 overflow-hidden lg:flex">
        <div class="border-line flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border p-2">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div class="border-line flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border p-2">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>

    @include('partials.editor-mobile-clues')
</div>
