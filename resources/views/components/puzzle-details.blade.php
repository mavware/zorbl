<div class="text-ink-faint tnum mt-1.5 flex min-w-0 items-center gap-x-2 overflow-hidden text-[12px] whitespace-nowrap label-classical [--label-tracking:0.06em]">
    <flux:tooltip :content="$crossword->puzzle_type->label()">
        <flux:icon :name="$crossword->puzzle_type->icon()" class="size-3 shrink-0"/>
    </flux:tooltip>

    <span class="shrink-0">{{ $crossword->width }}&times;{{ $crossword->height }}</span>

    <span aria-hidden="true" class="shrink-0">&middot;</span>

    <span class="truncate">{{ $crossword->updated_at->diffForHumans() }}</span>
</div>
