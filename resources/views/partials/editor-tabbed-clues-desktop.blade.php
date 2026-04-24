{{-- Desktop tabbed clue panel — switches between Across and Down.
     Bound directly to the parent `direction` Alpine state so clicking a cell
     (which sets `direction`) automatically surfaces the matching tab, and
     clicking a tab updates the grid's active direction. --}}
@php
    $freestyleUnlocked = $this->puzzleType === \App\Enums\PuzzleType::Freestyle && ! $this->freestyleLocked;
@endphp
@if ($freestyleUnlocked)
    <div class="text-fg-muted flex h-full items-center justify-center px-4 text-center text-sm">
        {{ __('Lock the grid to start writing clues.') }}
    </div>
@else
<div class="flex min-h-0 flex-col overflow-hidden">
    <div class="flex shrink-0 border-b border-line">
        <button
            type="button"
            x-on:click="direction = 'across'"
            :class="text-fg direction === 'across' ? 'border-zinc-800 dark:border-zinc-200 ' : 'border-transparent text-zinc-600'"
            class="border-b-2 px-4 py-2 text-sm font-medium"
        >{{ __('Across') }}</button>
        <button
            type="button"
            x-on:click="direction = 'down'"
            :class="text-fg direction === 'down' ? 'border-zinc-800 dark:border-zinc-200 ' : 'border-transparent text-zinc-600'"
            class="border-b-2 px-4 py-2 text-sm font-medium"
        >{{ __('Down') }}</button>
    </div>
    <div class="flex min-h-0 flex-1 overflow-hidden pt-2">
        <div x-show="direction === 'across'" class="flex min-h-0 w-full flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div x-show="direction === 'down'" class="flex min-h-0 w-full flex-col overflow-hidden" x-cloak>
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>
</div>
@endif
