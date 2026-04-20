{{-- Desktop tabbed clue panel — switches between Across and Down.
     Bound directly to the parent `direction` Alpine state so clicking a cell
     (which sets `direction`) automatically surfaces the matching tab, and
     clicking a tab updates the grid's active direction. --}}
<div class="flex min-h-0 flex-col overflow-hidden">
    <div class="flex shrink-0 border-b border-zinc-200 dark:border-zinc-700">
        <button
            type="button"
            x-on:click="direction = 'across'"
            :class="direction === 'across' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
            class="border-b-2 px-4 py-2 text-sm font-medium"
        >{{ __('Across') }}</button>
        <button
            type="button"
            x-on:click="direction = 'down'"
            :class="direction === 'down' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
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
