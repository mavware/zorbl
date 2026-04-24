@php
    /** @var string $direction 'across' or 'down' */
    $label = $direction === 'across' ? __('Across') : __('Down');
    $computed = $direction === 'across' ? 'computedCluesAcross' : 'computedCluesDown';
    $panelRef = $direction === 'across' ? 'acrossPanel' : 'downPanel';
    $dirLetter = $direction[0]; // 'a' or 'd'
    $freestyleUnlocked = $this->puzzleType === \App\Enums\PuzzleType::Freestyle && ! $this->freestyleLocked;
@endphp
@if ($freestyleUnlocked)
    <div class="text-fg-muted flex h-full items-center justify-center px-4 text-center text-sm">
        {{ __('Lock the grid to start writing clues.') }}
    </div>
@else
<flux:heading size="sm" class="mb-2 shrink-0">{{ $label }}</flux:heading>
<div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="{{ $panelRef }}">
    <template x-for="clue in {{ $computed }}" :key="'{{ $direction }}-' + clue.number">
        <div
            x-on:click="selectClue('{{ $direction }}', clue.number, $event)"
            x-on:focusin="selectClue('{{ $direction }}', clue.number, $event)"
            x-on:keydown.tab.prevent="focusNextClue($el, '{{ $direction }}', false)"
            x-on:keydown.shift.tab.prevent="focusNextClue($el, '{{ $direction }}', true)"
            :class="[
activeClueNumber === clue.number && direction === '{{ $direction }}' ? 'bg-blue-100 dark:bg-blue-900/40' : 'hover:bg-zinc-100 dark:hover:bg-zinc-700/50',
isClueIncomplete('{{ $direction }}') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
]"
            class="cursor-pointer rounded px-2 py-1"
            :id="'clue-{{ $direction }}-' + clue.number"
        >
            <div class="flex items-start gap-1.5">
                <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                <div class="clue-content flex-1">
                    <input
                        type="text"
                        x-model="clue.clue"
                        x-on:blur="markDirty()"
                        placeholder="{{ __('Enter clue...') }}"
                        class="w-full border-0 bg-transparent p-0 text-sm text-zinc-800 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                    />
                    <div class="flex items-center gap-1">
                        <span class="text-xs text-zinc-500 cursor-text" x-text="'(' + clue.length + ')'"
                              x-on:click="$event.target.closest('.clue-content').querySelector('input').focus()"></span>
                        @include('partials.clue-quality-icon', ['dir' => $direction])
                        <flux:tooltip content="{{ __('Clue library') }}" x-show="activeClueNumber === clue.number && direction === '{{ $direction }}'">
                            <button
                                type="button"
                                x-on:click.stop="toggleSuggestions()"
                                class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300 cursor-pointer"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 20 20"
                                     fill="currentColor">
                                    <path
                                        d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                </svg>
                            </button>
                        </flux:tooltip>
                        <flux:tooltip content="{{ __('Suggest words') }}" x-show="activeClueNumber === clue.number && direction === '{{ $direction }}'">
                            <button
                                type="button"
                                x-on:click.stop="toggleWordSuggestions()"
                                class="inline-flex items-center rounded px-1 py-0.5 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:text-blue-400 dark:hover:bg-blue-900/20 dark:hover:text-blue-300 cursor-pointer"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 20 20"
                                     fill="currentColor">
                                    <path d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.044a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2v-.044c0-1.013.762-1.957 1.815-2.825A6 6 0 0 0 10 1ZM8 18a2 2 0 1 0 4 0H8Z"/>
                                </svg>
                            </button>
                        </flux:tooltip>
                    </div>
                </div>
            </div>

            {{-- Clue suggestions --}}
            <template
                x-if="activeClueNumber === clue.number && direction === '{{ $direction }}' && showSuggestions && (clueSuggestions.length > 0 || clueSuggestionsLoading)">
                <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                    <template x-if="clueSuggestionsLoading">
                        <span class="text-xs text-zinc-500 italic">{{ __('Loading suggestions...') }}</span>
                    </template>
                    <template x-if="!clueSuggestionsLoading">
                        <div class="space-y-0.5">
                            <span
                                class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                            <template x-for="(suggestion, idx) in clueSuggestions" :key="'s{{ $dirLetter }}-' + idx">
                                <div
                                    x-on:click.stop="useClue(clue, suggestion.clue)"
                                    class="clue-content cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-700 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                    :title="suggestion.puzzle + ' — ' + suggestion.author"
                                >
                                    <span x-text="suggestion.clue"></span>
                                    <span class="text-fg-subtle"
                                          x-text="' — ' + suggestion.author"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Word suggestions --}}
            <template
                x-if="activeClueNumber === clue.number && direction === '{{ $direction }}' && showWordSuggestions && (wordSuggestions.length > 0 || wordSuggestionsLoading)">
                <div class="mt-1 ml-5 border-l-2 border-blue-300 pl-2 dark:border-blue-600">
                    <template x-if="wordSuggestionsLoading">
                        <span class="text-xs text-zinc-500 italic">{{ __('Finding words...') }}</span>
                    </template>
                    <template x-if="!wordSuggestionsLoading">
                        <div class="space-y-0.5">
                            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">{{ __('Word suggestions') }}</span>
                            <template x-for="(suggestion, idx) in wordSuggestions" :key="'w{{ $dirLetter }}-' + idx">
                                <div
                                    x-on:click.stop="applyWordSuggestion(suggestion.word)"
                                    class="clue-content cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-700 hover:bg-blue-50 dark:text-zinc-400 dark:hover:bg-blue-900/20"
                                >
                                    <span x-text="suggestion.word"></span>
                                    <span class="text-fg-subtle" x-text="'(' + suggestion.score + ')'"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </template>
</div>
@endif
