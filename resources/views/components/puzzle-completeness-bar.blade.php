@php($completeness = $crossword->completeness())

<div class="mt-2 flex items-center gap-2">
    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div
            class="h-full rounded-full transition-all {{ $completeness['percentage'] === 100 ? 'bg-emerald-500' : ($completeness['percentage'] >= 60 ? 'bg-amber-500' : 'bg-zinc-400') }}"
            style="width: {{ $completeness['percentage'] }}%"
        ></div>
    </div>
    <span class="text-xs tabular-nums text-zinc-500">{{ $completeness['percentage'] }}%</span>
</div>
