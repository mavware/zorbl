{{-- Mobile suggestions bottom sheet. Hidden at `lg` and above, where the
     persistent pane lives beside the grid. Whenever a cell is selected a peek
     strip sits at the bottom of the screen naming the slot; tapping it slides
     the sheet up to half the viewport with the same Words / Clue library
     tabs as the pane. The grid above stays interactive, so changing the
     selection updates the sheet in place. Picking a word or clue collapses
     it back to the strip. Included once by the editor page, after the layout. --}}
@php
    $freestyleUnlocked = $this->puzzleType === \App\Enums\PuzzleType::Freestyle && ! $this->freestyleLocked;
@endphp
@unless ($freestyleUnlocked)
    {{-- Reserve room so the peek strip never covers the last mobile clue. --}}
    <div class="h-14 shrink-0 lg:hidden" x-show="suggestionsSlot" x-cloak aria-hidden="true"></div>

    <div
        x-ref="suggestionsSheet"
        x-show="suggestionsSlot"
        x-cloak
        role="region"
        aria-label="{{ __('Suggestions') }}"
        data-suggestions-sheet
        :class="suggestionsSheetOpen ? 'h-[50dvh]' : 'h-12'"
        class="border-line bg-surface fixed inset-x-0 bottom-0 z-30 flex flex-col overflow-hidden rounded-t-xl border-t shadow-[0_-4px_16px_rgba(0,0,0,0.15)] transition-[height] duration-200 lg:hidden"
    >
        {{-- Peek strip / header. Always visible; tap to expand or collapse. --}}
        <button
            type="button"
            x-on:click="toggleSuggestionsSheet()"
            :aria-expanded="suggestionsSheetOpen ? 'true' : 'false'"
            class="flex h-12 w-full shrink-0 items-center gap-2 px-4 text-start"
            data-test="suggestions-sheet-toggle"
        >
            <flux:icon.light-bulb variant="mini" class="size-4 shrink-0 text-amber-500" />
            <div class="text-fg flex min-w-0 flex-1 items-baseline gap-1.5 truncate text-sm font-medium">
                <span class="tnum" x-text="suggestionsSlot?.number"></span>
                <span x-text="suggestionsSlot?.direction === 'across' ? '{{ __('Across') }}' : '{{ __('Down') }}'"></span>
                <span class="text-fg-subtle">·</span>
                <span class="text-fg-muted font-mono text-[13px] tracking-[0.18em]" x-text="suggestionsSlot?.pattern"></span>
                <span class="text-fg-subtle ms-1 truncate text-xs font-normal" x-show="!suggestionsSheetOpen">{{ __('Suggestions') }}</span>
                <span class="text-fg-subtle tnum ms-1 truncate text-xs font-normal" x-show="suggestionsSheetOpen" x-cloak>
                    <span x-show="suggestionsTab === 'words' ? wordSuggestionsLoading : clueSuggestionsLoading">{{ __('Searching…') }}</span>
                    <span x-show="!(suggestionsTab === 'words' ? wordSuggestionsLoading : clueSuggestionsLoading)"
                          x-text="suggestionsList.length + ' ' + (suggestionsList.length === 1 ? '{{ __('match') }}' : '{{ __('matches') }}')"></span>
                </span>
            </div>
            <span class="text-fg-subtle shrink-0 transition-transform duration-200" :class="suggestionsSheetOpen ? 'rotate-180' : ''">
                <flux:icon.chevron-up variant="mini" class="size-4" />
            </span>
        </button>

        {{-- Expanded body --}}
        <div x-show="suggestionsSheetOpen" x-cloak class="flex min-h-0 flex-1 flex-col">
            @include('partials.editor-suggestions-list')
        </div>
    </div>
@endunless
