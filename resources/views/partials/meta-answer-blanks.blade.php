{{-- The row of blanks inside a metaAnswerEntry Alpine scope: one underscored
     cell per letter of the answer, grouped so lines only break between
     words. A visually hidden input behind them takes the typing; clicking
     anywhere on the row focuses it. Expects `$tone` ('purple' while open,
     'red' holding a wrong answer, 'emerald' once correct, 'zinc' once
     submitted blind) and `$locked`. --}}
@php
    $slotTone = match ($tone) {
        'emerald' => 'border-emerald-500 text-emerald-800 dark:border-emerald-500 dark:text-emerald-200',
        'zinc' => 'border-zinc-400 text-zinc-800 dark:border-zinc-500 dark:text-zinc-200',
        'red' => 'border-red-400 text-red-700 dark:border-red-500 dark:text-red-300',
        default => 'border-purple-400 text-purple-900 dark:border-purple-500 dark:text-purple-100',
    };
@endphp
<div
    @class([
        'flex flex-wrap items-end gap-x-5 gap-y-2 select-none',
        'cursor-text' => ! $locked,
    ])
    x-on:click="focusInput()"
    role="group"
    aria-label="{{ __('Your answer') }}"
    data-test="meta-answer-blanks"
>
    @unless($locked)
        <input
            x-ref="input"
            type="text"
            class="sr-only"
            x-bind:maxlength="slotCount"
            x-bind:value="letters"
            x-on:input="onInput($event)"
            x-on:keydown.enter.prevent="submit()"
            x-on:focus="focused = true"
            x-on:blur="focused = false"
            autocomplete="off"
            autocapitalize="characters"
            autocorrect="off"
            spellcheck="false"
            enterkeyhint="done"
            aria-label="{{ __('Type your answer') }}"
            data-test="meta-answer-input"
        >
    @endunless

    <template x-for="(word, wordIndex) in words" :key="wordIndex">
        <span class="inline-flex items-end gap-x-1">
            <template x-for="piece in word" :key="piece.type + piece.index + piece.char">
                <span>
                    <template x-if="piece.type === 'slot'">
                        <span
                            class="inline-flex h-9 w-7 items-center justify-center border-b-2 font-mono text-xl font-semibold uppercase transition-colors {{ $slotTone }}"
                            x-bind:class="{ 'border-b-[3px] animate-pulse': !locked && focused && piece.index === activeIndex }"
                            x-text="letterAt(piece.index)"
                        ></span>
                    </template>
                    <template x-if="piece.type === 'literal'">
                        <span
                            class="inline-flex h-9 w-4 items-center justify-center font-mono text-xl {{ $slotTone }}"
                            x-text="piece.char"
                        ></span>
                    </template>
                </span>
            </template>
        </span>
    </template>
</div>
