@php($completeness = $crossword->completeness())

<div>
    <div class="meta-classical flex items-center justify-between gap-2">
        <span>{{ $completeness['percentage'] === 100 ? __('Complete') : __('Grid & clues') }}</span>
        <span class="font-classical text-ink tnum text-[15px] font-medium normal-case tracking-normal">{{ $completeness['percentage'] }}%</span>
    </div>
    <div class="bg-border mt-1.5 h-0.5 w-full overflow-hidden">
        <div class="h-full bg-amber-400 transition-all" style="width: {{ $completeness['percentage'] }}%"></div>
    </div>
</div>
