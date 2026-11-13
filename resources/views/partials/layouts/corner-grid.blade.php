{{-- Shared body of the four corner layouts: the grid sits in one corner and
     the clues wrap around it, like the wrap-around PDF layouts. Across runs
     in two columns along the grid's free horizontal edge (below a top grid,
     above a bottom one) and Down takes the full-height column beside it.
     Each direction scrolls on its own, so clues never jump between columns
     while they are being typed.

     The grid is held to what the viewport leaves once the Across clues have
     their share, so the grid and both clue areas stay on screen together.
     Below `lg` the wrappers drop out of the box tree and the layout stacks
     like every other one.

     @var bool $gridOnRight
     @var bool $gridAtBottom --}}
<div class="flex flex-1 gap-4 overflow-hidden max-lg:flex-col lg:max-h-[calc(100dvh-8rem)]" data-test="corner-layout">
    <div @class([
        'flex min-w-0 flex-1 gap-4 max-lg:contents lg:overflow-y-auto',
        'flex-col' => ! $gridAtBottom,
        'flex-col-reverse' => $gridAtBottom,
        'lg:order-2' => $gridOnRight,
    ])>
        <div
            class="flex shrink-0 max-lg:contents lg:[&_[role=grid]]:max-w-[min(100%,calc((100dvh-20rem)*var(--grid-aspect)))]"
            :style="'--grid-aspect: ' + (width / height)"
        >
            @include('partials.editor-grid')
        </div>

        <div class="hidden min-h-40 flex-1 flex-col overflow-hidden lg:flex">
            @include('partials.editor-clue-panel', ['direction' => 'across', 'columns' => 2])
        </div>
    </div>

    <div @class(['hidden w-64 flex-col overflow-hidden lg:flex', 'lg:order-1' => $gridOnRight])>
        @include('partials.editor-clue-panel', ['direction' => 'down', 'columns' => 1])
    </div>

    @include('partials.editor-mobile-clues')
</div>
