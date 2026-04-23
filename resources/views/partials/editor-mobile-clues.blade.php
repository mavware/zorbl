        {{-- Mobile clue panels --}}
        <div class="lg:hidden">
            <div class="flex border-b border-zinc-300 dark:border-zinc-700">
                <button
                    x-on:click="direction = 'across'"
                    :class="direction === 'across' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-600'"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Across') }}</button>
                <button
                    x-on:click="direction = 'down'"
                    :class="direction === 'down' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-600'"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Down') }}</button>
            </div>
            <div class="max-h-48 space-y-0.5 overflow-y-auto py-2">
                <template x-if="direction === 'across'">
                    <div>
                        <template x-for="clue in computedCluesAcross" :key="'m-across-' + clue.number">
                            <div
                                x-on:click="selectClue('across', clue.number, $event)"
                                x-on:focusin="selectClue('across', clue.number, $event)"
                                x-on:keydown.tab.prevent="focusNextClue($el, 'across', false)"
                                x-on:keydown.shift.tab.prevent="focusNextClue($el, 'across', true)"
                                :class="[
                                        activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
                                        isClueIncomplete('across') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
                                    ]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            x-model="clue.clue"
                                            x-on:blur="markDirty()"
                                            placeholder="{{ __('Enter clue...') }}"
                                            class="w-full border-0 bg-transparent p-0 text-sm text-zinc-800 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                        />
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                            @include('partials.clue-quality-icon', ['dir' => 'across'])
                                            <flux:tooltip content="{{ __('Clue library') }}" x-show="activeClueNumber === clue.number && direction === 'across'">
                                                <button
                                                    type="button"
                                                    x-on:click.stop="toggleSuggestions()"
                                                    class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300 cursor-pointer"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5"
                                                         viewBox="0 0 20 20" fill="currentColor">
                                                        <path
                                                            d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                                    </svg>
                                                </button>
                                            </flux:tooltip>
                                            <flux:tooltip content="{{ __('Suggest words') }}" x-show="activeClueNumber === clue.number && direction === 'across'">
                                                <button
                                                    type="button"
                                                    x-on:click.stop="toggleWordSuggestions()"
                                                    class="inline-flex items-center rounded px-1 py-0.5 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:text-blue-400 dark:hover:bg-blue-900/20 dark:hover:text-blue-300 cursor-pointer"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5"
                                                         viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.044a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2v-.044c0-1.013.762-1.957 1.815-2.825A6 6 0 0 0 10 1ZM8 18a2 2 0 1 0 4 0H8Z"/>
                                                    </svg>
                                                </button>
                                            </flux:tooltip>
                                        </div>
                                    </div>
                                </div>

                                {{-- Clue suggestions (mobile) --}}
                                <template
                                    x-if="activeClueNumber === clue.number && direction === 'across' && showSuggestions && clueSuggestions.length > 0 && !clueSuggestionsLoading">
                                    <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                        <span
                                            class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                        <template x-for="(suggestion, idx) in clueSuggestions.slice(0, 5)"
                                                  :key="'msa-' + idx">
                                            <div
                                                x-on:click.stop="useClue(clue, suggestion.clue)"
                                                class="cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-700 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                            >
                                                <span x-text="suggestion.clue"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Word suggestions (mobile) --}}
                                <template
                                    x-if="activeClueNumber === clue.number && direction === 'across' && showWordSuggestions && wordSuggestions.length > 0 && !wordSuggestionsLoading">
                                    <div class="mt-1 ml-5 border-l-2 border-blue-300 pl-2 dark:border-blue-600">
                                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">{{ __('Word suggestions') }}</span>
                                        <template x-for="(suggestion, idx) in wordSuggestions.slice(0, 10)"
                                                  :key="'mwa-' + idx">
                                            <div
                                                x-on:click.stop="applyWordSuggestion(suggestion.word)"
                                                class="cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-700 hover:bg-blue-50 dark:text-zinc-400 dark:hover:bg-blue-900/20"
                                            >
                                                <span x-text="suggestion.word"></span>
                                                <span class="text-zinc-500 dark:text-zinc-500" x-text="'(' + suggestion.score + ')'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="direction === 'down'">
                    <div>
                        <template x-for="clue in computedCluesDown" :key="'m-down-' + clue.number">
                            <div
                                x-on:click="selectClue('down', clue.number, $event)"
                                x-on:focusin="selectClue('down', clue.number, $event)"
                                x-on:keydown.tab.prevent="focusNextClue($el, 'down', false)"
                                x-on:keydown.shift.tab.prevent="focusNextClue($el, 'down', true)"
                                :class="[
                                        activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
                                        isClueIncomplete('down') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
                                    ]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                    <div class="flex-1">
                                        <input
                                            type="text"
                                            x-model="clue.clue"
                                            x-on:blur="markDirty()"
                                            placeholder="{{ __('Enter clue...') }}"
                                            class="w-full border-0 bg-transparent p-0 text-sm text-zinc-800 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                        />
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-zinc-500" x-text="'(' + clue.length + ')'"></span>
                                            @include('partials.clue-quality-icon', ['dir' => 'down'])
                                            <flux:tooltip content="{{ __('Clue library') }}" x-show="activeClueNumber === clue.number && direction === 'down'">
                                                <button
                                                    type="button"
                                                    x-on:click.stop="toggleSuggestions()"
                                                    class="inline-flex items-center rounded px-1 py-0.5 text-amber-500 transition-colors hover:bg-amber-50 hover:text-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/20 dark:hover:text-amber-300 cursor-pointer"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5"
                                                         viewBox="0 0 20 20" fill="currentColor">
                                                        <path
                                                            d="M9 4.804A7.968 7.968 0 0 0 5.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 0 1 5.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0 1 14.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0 0 14.5 4c-1.669 0-3.218.51-4.5 1.385V15"/>
                                                    </svg>
                                                </button>
                                            </flux:tooltip>
                                            <flux:tooltip content="{{ __('Suggest words') }}" x-show="activeClueNumber === clue.number && direction === 'down'">
                                                <button
                                                    type="button"
                                                    x-on:click.stop="toggleWordSuggestions()"
                                                    class="inline-flex items-center rounded px-1 py-0.5 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:text-blue-400 dark:hover:bg-blue-900/20 dark:hover:text-blue-300 cursor-pointer"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5"
                                                         viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.044a2 2 0 0 0 2 2h0a2 2 0 0 0 2-2v-.044c0-1.013.762-1.957 1.815-2.825A6 6 0 0 0 10 1ZM8 18a2 2 0 1 0 4 0H8Z"/>
                                                    </svg>
                                                </button>
                                            </flux:tooltip>
                                        </div>
                                    </div>
                                </div>

                                {{-- Clue suggestions (mobile) --}}
                                <template
                                    x-if="activeClueNumber === clue.number && direction === 'down' && showSuggestions && clueSuggestions.length > 0 && !clueSuggestionsLoading">
                                    <div class="mt-1 ml-5 border-l-2 border-amber-300 pl-2 dark:border-amber-600">
                                        <span
                                            class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Clue library') }}</span>
                                        <template x-for="(suggestion, idx) in clueSuggestions.slice(0, 5)"
                                                  :key="'msd-' + idx">
                                            <div
                                                x-on:click.stop="useClue(clue, suggestion.clue)"
                                                class="cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-700 hover:bg-amber-50 dark:text-zinc-400 dark:hover:bg-amber-900/20"
                                            >
                                                <span x-text="suggestion.clue"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Word suggestions (mobile) --}}
                                <template
                                    x-if="activeClueNumber === clue.number && direction === 'down' && showWordSuggestions && wordSuggestions.length > 0 && !wordSuggestionsLoading">
                                    <div class="mt-1 ml-5 border-l-2 border-blue-300 pl-2 dark:border-blue-600">
                                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">{{ __('Word suggestions') }}</span>
                                        <template x-for="(suggestion, idx) in wordSuggestions.slice(0, 10)"
                                                  :key="'mwd-' + idx">
                                            <div
                                                x-on:click.stop="applyWordSuggestion(suggestion.word)"
                                                class="cursor-pointer rounded px-1 py-0.5 text-xs text-zinc-700 hover:bg-blue-50 dark:text-zinc-400 dark:hover:bg-blue-900/20"
                                            >
                                                <span x-text="suggestion.word"></span>
                                                <span class="text-zinc-500 dark:text-zinc-500" x-text="'(' + suggestion.score + ')'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
