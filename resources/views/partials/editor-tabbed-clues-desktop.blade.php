{{-- Desktop tabbed clue panel — switches between Across and Down.
     Uses a local `cluesTab` Alpine state so tabbed layouts don't collide with
     the mobile tabs' `mobileClueTab`. --}}
<div class="flex min-h-0 flex-col overflow-hidden" x-data="{ cluesTab: 'across' }">
    <div class="flex shrink-0 border-b border-zinc-200 dark:border-zinc-700">
        <button
            type="button"
            x-on:click="cluesTab = 'across'"
            :class="cluesTab === 'across' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
            class="border-b-2 px-4 py-2 text-sm font-medium"
        >{{ __('Across') }}</button>
        <button
            type="button"
            x-on:click="cluesTab = 'down'"
            :class="cluesTab === 'down' ? 'border-zinc-800 text-zinc-900 dark:border-zinc-200 dark:text-zinc-100' : 'border-transparent text-zinc-500'"
            class="border-b-2 px-4 py-2 text-sm font-medium"
        >{{ __('Down') }}</button>
    </div>
    <div class="flex min-h-0 flex-1 overflow-hidden pt-2">
        <div x-show="cluesTab === 'across'" class="flex min-h-0 w-full flex-col overflow-hidden">
            @include('partials.editor-clue-panel', ['direction' => 'across'])
        </div>
        <div x-show="cluesTab === 'down'" class="flex min-h-0 w-full flex-col overflow-hidden" x-cloak>
            @include('partials.editor-clue-panel', ['direction' => 'down'])
        </div>
    </div>
</div>
