@php($completeness = $crossword->completeness())

<div>
    <div class="meta-classical tnum mb-[7px] flex items-center justify-between gap-2">
        <span class="max-sm:hidden">{{ $completeness['percentage'] === 100 ? __('Complete') : __('Grid & clues') }}</span>
        <span class="max-sm:ml-auto">{{ $completeness['percentage'] }}%</span>
    </div>
    <div class="bg-border h-[2px] w-full overflow-hidden">
        <div class="h-full bg-amber-400" style="width: {{ $completeness['percentage'] }}%"></div>
    </div>
</div>
