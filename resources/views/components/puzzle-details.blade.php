<div class="meta-classical mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
    <flux:tooltip :content="$crossword->puzzle_type->label()">
        <flux:icon :name="$crossword->puzzle_type->icon()" class="size-3"/>
    </flux:tooltip>

    <span class="tnum whitespace-nowrap">{{ $crossword->width }}&times;{{ $crossword->height }}</span>

    <span aria-hidden="true">&middot;</span>

    <span class="whitespace-nowrap">{{ $crossword->updated_at->diffForHumans() }}</span>
</div>
