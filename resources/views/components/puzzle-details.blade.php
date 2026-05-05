@php($completeness = $crossword->completeness())

<div class="mt-1 flex gap-2 text-xs">
    <flux:tooltip :content="$crossword->puzzle_type->label()">
        <flux:icon :name="$crossword->puzzle_type->icon()" class="size-3"/>
    </flux:tooltip>

    <div>
        {{ $crossword->width }}&times;{{ $crossword->height }}
    </div>

    <div>
        {{ $crossword->updated_at->diffForHumans() }}
    </div>
</div>
