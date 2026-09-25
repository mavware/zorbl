        {{-- Mobile clue panels --}}
        @php
            $freestyleUnlocked = $this->puzzleType === \App\Enums\PuzzleType::Freestyle && ! $this->freestyleLocked;
        @endphp
        @if (! $freestyleUnlocked)
        <div class="lg:hidden" :class="{ 'editor-complete-flash': cluesCompleteFlash }">
            <div class="flex border-b border-line">
                <button
                    x-on:click="direction = 'across'"
                    :class="['text-fg', direction === 'across' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600']"
                    class="border-b-2 px-4 py-2 text-sm font-medium"
                >{{ __('Across') }}</button>
                <button
                    x-on:click="direction = 'down'"
                    :class="['text-fg', direction === 'down' ? 'border-zinc-800 dark:border-zinc-200' : 'border-transparent text-zinc-600']"
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
                                x-on:keydown.enter.prevent="focusNextClue($el, 'across', $event.shiftKey)"
                                :class="[
activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
isClueIncomplete('across') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                    <div class="flex-1">
                                        <textarea
                                            rows="1"
                                            x-model="clue.clue"
                                            x-init="fitClueTextarea($el)"
                                            x-effect="clue.clue; fitClueTextarea($el)"
                                            x-on:input="fitClueTextarea($el)"
                                            x-on:focus="fitClueTextarea($el)"
                                            x-on:blur="markDirty()"
                                            placeholder="{{ __('Enter clue...') }}"
                                            class="field-sizing-content block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-sm leading-snug text-zinc-800 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                        ></textarea>
                                        <div class="flex items-center gap-1">
                                            @include('partials.clue-quality-icon', ['dir' => 'across'])
                                            <flux:tooltip content="{{ __('Clue library') }}" x-show="activeClueNumber === clue.number && direction === 'across'">
                                                <button
                                                    type="button"
                                                    x-on:click.stop="openSuggestionsSheet('clues')"
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
                                                    x-on:click.stop="openSuggestionsSheet('words')"
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
                                x-on:keydown.enter.prevent="focusNextClue($el, 'down', $event.shiftKey)"
                                :class="[
activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
isClueIncomplete('down') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px text-xs font-bold text-zinc-600" x-text="clue.displayNumber"></span>
                                    <div class="flex-1">
                                        <textarea
                                            rows="1"
                                            x-model="clue.clue"
                                            x-init="fitClueTextarea($el)"
                                            x-effect="clue.clue; fitClueTextarea($el)"
                                            x-on:input="fitClueTextarea($el)"
                                            x-on:focus="fitClueTextarea($el)"
                                            x-on:blur="markDirty()"
                                            placeholder="{{ __('Enter clue...') }}"
                                            class="field-sizing-content block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-sm leading-snug text-zinc-800 placeholder-zinc-400 focus:ring-0 dark:text-zinc-300 dark:placeholder-zinc-500"
                                        ></textarea>
                                        <div class="flex items-center gap-1">
                                            @include('partials.clue-quality-icon', ['dir' => 'down'])
                                            <flux:tooltip content="{{ __('Clue library') }}" x-show="activeClueNumber === clue.number && direction === 'down'">
                                                <button
                                                    type="button"
                                                    x-on:click.stop="openSuggestionsSheet('clues')"
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
                                                    x-on:click.stop="openSuggestionsSheet('words')"
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
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
        @endif
