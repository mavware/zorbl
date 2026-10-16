{{-- Total completion ring for the Publish / Unpublish button. One
     SVG circle with pathLength="100", so the pooled cells-and-clues
     percentage is its dash offset directly. The track is the button's own
     text colour at low opacity; the arc takes the fill colour until the
     puzzle is ready, when it matches the button so the solid amber state
     stays clean. --}}
<svg class="size-4 shrink-0 -rotate-90" viewBox="0 0 16 16" fill="none" aria-hidden="true" data-test="editor-publish-progress">
    <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="2.5" class="opacity-25"/>
    <circle
        cx="8" cy="8" r="6.5"
        stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
        pathLength="100" stroke-dasharray="100"
        class="transition-[stroke-dashoffset] duration-300"
        :class="isReadyToPublish ? '' : totalFillColorClass"
        :stroke-dashoffset="100 - totalFillPercent"
        x-show="totalFillPercent > 0"
    />
</svg>
<span class="sr-only" x-text="totalFillPercent + '% {{ __('complete') }}'"></span>
