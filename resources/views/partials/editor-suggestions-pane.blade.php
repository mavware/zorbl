{{-- Persistent suggestions pane. A fixed-width column that always reflects the
     selected slot: word candidates for the current letter pattern, and the
     clue library for the current answer. Rendered by the desktop layout
     partials only; below `lg` the bottom sheet (editor-suggestions-sheet)
     shows the same lists.

     Focus the pane (click or Tab), then ↑/↓ highlight and preview a
     candidate in the grid, Enter places it and Esc returns to the grid. --}}
<aside
    x-ref="suggestionsPane"
    x-init="mountSuggestionsPane()"
    x-on:keydown="handleSuggestionsKeydown($event)"
    x-on:focusout="onSuggestionsPaneBlur($event)"
    tabindex="0"
    aria-label="{{ __('Suggestions') }}"
    data-suggestions-pane
    :class="suggestionsPaneCollapsed ? 'w-10' : 'w-[300px]'"
    class="border-line bg-surface hidden shrink-0 flex-col overflow-hidden rounded-lg border outline-none transition-[width] duration-150 focus-visible:ring-2 focus-visible:ring-blue-500 lg:flex"
>
    {{-- Collapsed strip --}}
    <div x-show="suggestionsPaneCollapsed" x-cloak class="flex h-full flex-col items-center gap-2 py-2">
        <flux:tooltip content="{{ __('Show suggestions') }}" position="left">
            <button
                type="button"
                x-on:click="toggleSuggestionsPane()"
                class="text-fg-muted hover:text-fg rounded p-1 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-700/50"
                aria-label="{{ __('Show suggestions') }}"
            >
                <flux:icon.light-bulb variant="mini" class="size-4" />
            </button>
        </flux:tooltip>
        <span class="text-fg-subtle text-[11px] font-medium tracking-wide [writing-mode:vertical-rl]">{{ __('Suggestions') }}</span>
    </div>

    {{-- Expanded pane --}}
    <div x-show="!suggestionsPaneCollapsed" class="flex min-h-0 flex-1 flex-col">
        {{-- Header: the slot being served --}}
        <div class="border-line flex shrink-0 items-start justify-between gap-2 border-b px-3 py-2">
            <div class="min-w-0 flex-1">
                <template x-if="suggestionsSlot">
                    <div>
                        <div class="text-fg flex items-baseline gap-1.5 truncate text-sm font-medium">
                            <span class="tnum" x-text="suggestionsSlot?.number"></span>
                            <span x-text="suggestionsSlot?.direction === 'across' ? '{{ __('Across') }}' : '{{ __('Down') }}'"></span>
                            <span class="text-fg-subtle">·</span>
                            <span class="text-fg-muted font-mono text-[13px] tracking-[0.18em]" x-text="suggestionsSlot?.pattern"></span>
                        </div>
                        <div class="text-fg-subtle tnum text-xs">
                            <span x-show="suggestionsTab === 'words' ? wordSuggestionsLoading : clueSuggestionsLoading">{{ __('Searching…') }}</span>
                            <span x-show="!(suggestionsTab === 'words' ? wordSuggestionsLoading : clueSuggestionsLoading)"
                                  x-text="suggestionsList.length + ' ' + (suggestionsList.length === 1 ? '{{ __('match') }}' : '{{ __('matches') }}')"></span>
                        </div>
                    </div>
                </template>
                <template x-if="!suggestionsSlot">
                    <div>
                        <div class="text-fg text-sm font-medium">{{ __('Suggestions') }}</div>
                        <div class="text-fg-subtle text-xs">{{ __('Select a cell to see candidates') }}</div>
                    </div>
                </template>
            </div>
            <flux:tooltip content="{{ __('Hide suggestions') }}">
                <button
                    type="button"
                    x-on:click="toggleSuggestionsPane()"
                    class="text-fg-subtle hover:text-fg -mr-1 rounded p-1 transition-colors hover:bg-zinc-100 dark:hover:bg-zinc-700/50"
                    aria-label="{{ __('Hide suggestions') }}"
                >
                    <flux:icon.chevron-double-right variant="mini" class="size-4" />
                </button>
            </flux:tooltip>
        </div>

        @include('partials.editor-suggestions-list')

        {{-- Keyboard hint --}}
        <div class="border-line text-fg-subtle flex shrink-0 items-center gap-2 border-t px-3 py-1.5 text-[11px]">
            <span><kbd class="font-sans">↑↓</kbd> {{ __('preview') }}</span>
            <span aria-hidden="true">·</span>
            <span><kbd class="font-sans">Enter</kbd> {{ __('place') }}</span>
            <span aria-hidden="true">·</span>
            <span><kbd class="font-sans">Esc</kbd> {{ __('back to grid') }}</span>
        </div>
    </div>
</aside>
