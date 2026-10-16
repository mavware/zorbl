{{-- The post-solve meta answer card, shared by the signed-in solver and the
     guest solve page. Both expose the same properties: the prompt, the
     answer's blank pattern, and the submission state (`metaAnswerSubmitted`,
     `metaAnswerCorrect`: true/false, or null when the puzzle keeps the
     result to itself, and `metaAnswerSubmission`).

     It sits beneath the puzzle from the start. There is no Submit button:
     the entry checks itself the moment the last blank is filled. Three
     states: `open` shows empty blanks; `retry`, after a wrong answer was
     flagged, keeps the wrong letters in place in red with a "not quite"
     line above, so the solver can back up and change some; `done` locks
     the blanks on the submitted letters, green when known correct, neutral
     when the builder chose not to reveal. The state is in the wire:key so
     Livewire swaps the whole card between states and the Alpine entry
     starts fresh rather than being morphed in place. --}}
@php
    $metaEntryState = $metaAnswerSubmitted ? ($metaAnswerCorrect === false ? 'retry' : 'done') : 'open';
@endphp
<div
    class="mt-6 rounded-xl border border-purple-200 bg-purple-50/50 p-5 dark:border-purple-900/40 dark:bg-purple-950/20"
    wire:key="meta-answer-section-{{ $metaEntryState }}"
    data-test="meta-answer-section"
    data-state="{{ $metaEntryState }}"
>
    <div class="mb-4 flex items-center gap-3">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
        </div>
        <div>
            <div class="font-semibold text-purple-800 dark:text-purple-200">{{ __('Meta Answer') }}</div>
            <div class="text-sm text-purple-700 dark:text-purple-400">{{ $metaAnswerPrompt }}</div>
        </div>
    </div>

    @if($metaEntryState === 'done')
        @php($metaEntryTone = $metaAnswerCorrect === true ? 'emerald' : 'zinc')
        <div
            @class([
                'rounded-lg border px-4 py-3',
                'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' => $metaEntryTone === 'emerald',
                'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800' => $metaEntryTone === 'zinc',
            ])
            role="status"
        >
            <div class="mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" @class(['size-5 shrink-0', 'text-emerald-600 dark:text-emerald-400' => $metaEntryTone === 'emerald', 'text-zinc-600 dark:text-zinc-400' => $metaEntryTone === 'zinc']) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <span @class(['font-medium', 'text-emerald-800 dark:text-emerald-200' => $metaEntryTone === 'emerald', 'text-zinc-800 dark:text-zinc-200' => $metaEntryTone === 'zinc'])>
                    {{ $metaAnswerCorrect === true ? __('Correct!') : __('Answer submitted!') }}
                </span>
            </div>
            <div
                x-data="metaAnswerEntry({ pattern: @js($metaAnswerPattern), initial: @js($metaAnswerSubmission), locked: true })"
                wire:ignore
                data-test="meta-answer-entry"
                data-pattern="{{ $metaAnswerPattern }}"
                data-locked
            >
                @include('partials.meta-answer-blanks', ['tone' => $metaEntryTone, 'locked' => true])
            </div>
        </div>
    @else
        @if($metaEntryState === 'retry')
            <div class="mb-3 flex items-center gap-2 text-sm text-red-700 dark:text-red-400" role="status">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                <span>{{ __('Not quite! Change some letters and try again.') }}</span>
            </div>
        @endif

        <div
            x-data="metaAnswerEntry({ pattern: @js($metaAnswerPattern), initial: @js($metaEntryState === 'retry' ? $metaAnswerSubmission : ''), submitted: @js($metaEntryState === 'retry' ? $metaAnswerSubmission : null), autofocus: @js($metaEntryState === 'retry') })"
            wire:ignore
            data-test="meta-answer-entry"
            data-pattern="{{ $metaAnswerPattern }}"
        >
            @include('partials.meta-answer-blanks', ['tone' => $metaEntryState === 'retry' ? 'red' : 'purple', 'locked' => false])
        </div>
        <flux:error name="metaAnswerSubmission" />
    @endif
</div>
