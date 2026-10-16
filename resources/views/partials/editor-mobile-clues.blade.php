        {{-- Mobile clue panels --}}
        @php
            $freestyleUnlocked = $this->puzzleType === \App\Enums\PuzzleType::Freestyle && ! $this->freestyleLocked;
        @endphp
        @if (! $freestyleUnlocked)
        <div class="lg:hidden" :class="{ 'editor-complete-flash': cluesCompleteFlash }">
            {{-- Tabs, with Publish at the far end of the same row. The button
                 carries the pooled completion ring, standing in for the
                 toolbar's progress meters that are hidden below `lg`. --}}
            <div class="flex items-center border-b border-line" data-test="editor-mobile-clue-tabs">
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

                <div class="ml-auto pb-1.5 pl-2">
                    @include('partials.editor-publish-button', ['testId' => 'editor-publish-button-mobile', 'withRing' => true])
                </div>
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
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); focusNextClue($el, 'across', false) }"
                                :class="[
activeClueNumber === clue.number && direction === 'across' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
isClueIncomplete('across') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px w-6 shrink-0 text-right text-xs font-bold tabular-nums text-zinc-600" x-text="clue.displayNumber"></span>
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
                                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); focusNextClue($el, 'down', false) }"
                                :class="[
activeClueNumber === clue.number && direction === 'down' ? 'bg-blue-100 dark:bg-blue-900/40' : '',
isClueIncomplete('down') && !clue.clue?.trim() ? 'ring-2 ring-amber-400 dark:ring-amber-500' : ''
]"
                                class="cursor-pointer rounded px-2 py-1"
                            >
                                <div class="flex items-start gap-1.5">
                                    <span class="mt-px w-6 shrink-0 text-right text-xs font-bold tabular-nums text-zinc-600" x-text="clue.displayNumber"></span>
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
        @else
        <div class="text-fg-muted space-y-2 px-4 py-3 text-center text-sm lg:hidden" data-testid="freestyle-mobile-instructions">
            <p>{{ __('Place words anywhere in the grid — no need to fill every cell.') }}</p>
            <p>{{ __('When you lock the grid, any unused cells disappear and the remaining words become your clues.') }}</p>
            <p>{{ __('Lock the grid to start writing clues.') }}</p>
        </div>
        @endif
