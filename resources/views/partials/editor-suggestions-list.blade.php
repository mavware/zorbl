{{-- The suggestions tabs and list. Shared by the desktop pane and the mobile
     bottom sheet, so both read the same slot, tab, lists and loading state.
     Rows get roomier tap targets inside the sheet. --}}
{{-- Tabs --}}
<div class="border-line flex shrink-0 border-b">
    <button
        type="button"
        x-on:click="setSuggestionsTab('words')"
        :class="suggestionsTab === 'words' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600'"
        :aria-selected="suggestionsTab === 'words'"
        role="tab"
        class="text-fg border-b-2 px-3 py-1.5 text-sm font-medium"
    >{{ __('Words') }}</button>
    <button
        type="button"
        x-on:click="setSuggestionsTab('clues')"
        :class="suggestionsTab === 'clues' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600'"
        :aria-selected="suggestionsTab === 'clues'"
        role="tab"
        class="text-fg border-b-2 px-3 py-1.5 text-sm font-medium"
    >{{ __('Clue library') }}</button>
</div>

{{-- Body: loading and empty states render in here so the pane never changes size --}}
<div class="min-h-0 flex-1 overflow-y-auto px-1 py-1" role="listbox">
    {{-- Words --}}
    <div x-show="suggestionsTab === 'words'">
        <template x-if="!suggestionsSlot">
            <p class="text-fg-muted px-2 py-6 text-center text-sm">{{ __('Click a cell in the grid to see words that fit.') }}</p>
        </template>
        <template x-if="suggestionsSlot && wordSuggestionsLoading">
            <p class="text-fg-muted px-2 py-6 text-center text-sm italic">{{ __('Finding words…') }}</p>
        </template>
        <template x-if="suggestionsSlot && !wordSuggestionsLoading && suggestionsSlot?.filled">
            <div class="px-2 py-6 text-center text-sm">
                <p class="text-fg-muted">{{ __('This entry is filled in.') }}</p>
                <button type="button" x-on:click="setSuggestionsTab('clues')" class="mt-2 text-amber-600 hover:underline dark:text-amber-400">{{ __('Browse the clue library') }}</button>
            </div>
        </template>
        <template x-if="suggestionsSlot && !wordSuggestionsLoading && !suggestionsSlot?.filled && wordSuggestions.length === 0">
            <p class="text-fg-muted px-2 py-6 text-center text-sm">
                {{ __('No words match') }} <span class="text-fg font-mono tracking-[0.18em]" x-text="suggestionsSlot?.pattern"></span>
            </p>
        </template>
        <template x-if="suggestionsSlot && !wordSuggestionsLoading && !suggestionsSlot?.filled && wordSuggestions.length > 0">
            <div class="space-y-px">
                <template x-for="(suggestion, idx) in wordSuggestions" :key="'pane-w-' + idx + '-' + suggestion.word">
                    <div
                        role="option"
                        :data-suggestion-index="idx"
                        :aria-selected="suggestionsTab === 'words' && suggestionsIndex === idx"
                        x-on:click="applyWordSuggestion(suggestion.word)"
                        :class="suggestionsTab === 'words' && suggestionsIndex === idx ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                        class="flex cursor-pointer items-center justify-between gap-2 rounded px-2 py-1 text-sm in-data-suggestions-sheet:px-3 in-data-suggestions-sheet:py-2.5 in-data-suggestions-sheet:text-base"
                    >
                        <span class="text-fg font-mono tracking-[0.12em]" x-text="suggestion.word"></span>
                        <span class="tnum shrink-0 text-xs font-medium" :class="suggestionScoreClass(suggestion.score)" x-text="Math.round(suggestion.score)"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Clue library --}}
    <div x-show="suggestionsTab === 'clues'" x-cloak>
        <template x-if="!suggestionsSlot">
            <p class="text-fg-muted px-2 py-6 text-center text-sm">{{ __('Click a cell in the grid to look up clues.') }}</p>
        </template>
        <template x-if="suggestionsSlot && !suggestionsSlot?.filled">
            <p class="text-fg-muted px-2 py-6 text-center text-sm">{{ __('Fill in the answer to search the clue library.') }}</p>
        </template>
        <template x-if="suggestionsSlot && suggestionsSlot?.filled && clueSuggestionsLoading">
            <p class="text-fg-muted px-2 py-6 text-center text-sm italic">{{ __('Loading clues…') }}</p>
        </template>
        <template x-if="suggestionsSlot && suggestionsSlot?.filled && !clueSuggestionsLoading && clueSuggestions.length === 0">
            <p class="text-fg-muted px-2 py-6 text-center text-sm">
                {{ __('No library clues for') }} <span class="text-fg font-mono tracking-[0.18em]" x-text="suggestionsSlot?.pattern"></span> {{ __('yet.') }}
            </p>
        </template>
        <template x-if="suggestionsSlot && suggestionsSlot?.filled && !clueSuggestionsLoading && clueSuggestions.length > 0">
            <div class="space-y-px">
                <template x-for="(suggestion, idx) in clueSuggestions" :key="'pane-c-' + idx">
                    <div
                        role="option"
                        :data-suggestion-index="idx"
                        :aria-selected="suggestionsTab === 'clues' && suggestionsIndex === idx"
                        x-on:click="activeClue && useClue(activeClue, suggestion.clue)"
                        :class="suggestionsTab === 'clues' && suggestionsIndex === idx ? 'bg-amber-50 dark:bg-amber-900/30' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                        class="cursor-pointer rounded px-2 py-1 text-sm in-data-suggestions-sheet:px-3 in-data-suggestions-sheet:py-2.5 in-data-suggestions-sheet:text-base"
                        :title="suggestion.puzzle + ' — ' + suggestion.author"
                    >
                        <span class="text-fg" x-text="suggestion.clue"></span>
                        <span class="text-fg-subtle text-xs" x-text="' — ' + suggestion.author"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
