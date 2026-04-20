{{-- Default editor layout: Across | Grid | Down on desktop; stacks Across+Down
     on the right when the grid is too wide to afford both side panels. --}}
@php $stackClues = $this->width > 17; @endphp
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]">
    @unless ($stackClues)
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
    @endunless

    @include('partials.editor-grid')

    @if ($stackClues)
        <div class="hidden w-64 flex-col gap-4 overflow-hidden lg:flex">
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @include('partials.editor-clue-panel', ['direction' => 'across'])
            </div>
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @include('partials.editor-clue-panel', ['direction' => 'down'])
            </div>
        </div>
    @else
        <div class="hidden w-64 flex-col overflow-hidden lg:flex">
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    @endif

    @include('partials.editor-mobile-clues')
</div>
