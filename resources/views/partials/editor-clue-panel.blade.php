@php
    /** @var string $direction 'across' or 'down' */
    $label = $direction === 'across' ? __('Across') : __('Down');
    $computed = $direction === 'across' ? 'computedCluesAcross' : 'computedCluesDown';
    $panelRef = $direction === 'across' ? 'acrossPanel' : 'downPanel';
    $freestyleUnlocked = $this->puzzleType === \App\Enums\PuzzleType::Freestyle && ! $this->freestyleLocked;
@endphp
@if ($freestyleUnlocked)
    <div class="text-fg-muted flex h-full flex-col items-center justify-center gap-2 px-4 text-center text-sm" :class="{ 'editor-complete-flash': cluesCompleteFlash }">
        <p>{{ __('Place words anywhere in the grid — no need to fill every cell.') }}</p>
        <p>{{ __('When you lock the grid, any unused cells disappear and the remaining words become your clues.') }}</p>
        <p>{{ __('Lock the grid to start writing clues.') }}</p>
    </div>
@else
<flux:heading size="sm" class="mb-2 shrink-0">{{ $label }}</flux:heading>
<div class="flex-1 space-y-0.5 overflow-y-auto" x-ref="{{ $panelRef }}" :class="{ 'editor-complete-flash': cluesCompleteFlash }">
    <template x-for="clue in {{ $computed }}" :key="'{{ $direction }}-' + clue.number">
        <div
            x-on:click="selectClue('{{ $direction }}', clue.number, $event)"
            x-on:focusin="selectClue('{{ $direction }}', clue.number, $event)"
            x-on:keydown.tab.prevent="focusNextClue($el, '{{ $direction }}', false)"
            x-on:keydown.shift.tab.prevent="focusNextClue($el, '{{ $direction }}', true)"
            x-on:keydown.enter.prevent="focusNextClue($el, '{{ $direction }}', $event.shiftKey)"
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
                        @include('partials.clue-quality-icon', ['dir' => $direction])
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
