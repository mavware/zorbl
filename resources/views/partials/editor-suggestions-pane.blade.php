{{-- Suggestions tab of the desktop settings sidebar. Always reflects the
     selected slot: word candidates for the current letter pattern, and the
     clue library for the current answer. Included by the shared settings
     form (editor-settings-form) for the sidebar surface only; below `lg`
     the bottom sheet (editor-suggestions-sheet) shows the same lists.

     The panel lives inside the form's nested Alpine scope, so it hands its
     element to mountSuggestionsPane() rather than registering an x-ref the
     grid component could not see. Lookups only run while the tab is
     visible; the tab button triggers them when it is picked.

     Focus the panel (click or Tab), then ↑/↓ highlight and preview a
     candidate in the grid, Enter places it and Esc returns to the grid.
     The shortcuts are deliberately not spelled out in the panel. --}}
<div
    id="{{ $settingsSurface }}-settings-panel-suggestions"
    role="tabpanel"
    aria-labelledby="{{ $settingsSurface }}-settings-tab-suggestions"
    x-show="settingsTab === 'suggestions'"
    x-cloak
    x-init="mountSuggestionsPane($el)"
    x-on:keydown="handleSuggestionsKeydown($event)"
    x-on:focusout="onSuggestionsPaneBlur($event)"
    tabindex="0"
    data-suggestions-pane
    data-test="settings-panel-suggestions"
    class="flex min-h-0 flex-1 flex-col outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
>
    {{-- Header: the slot being served --}}
    <div class="border-line shrink-0 border-b pb-2">
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
                <div class="text-fg text-sm font-medium">{{ __('Ideas') }}</div>
                <div class="text-fg-subtle text-xs">{{ __('Select a cell to see candidates') }}</div>
            </div>
        </template>
    </div>

    @include('partials.editor-suggestions-list')
</div>
