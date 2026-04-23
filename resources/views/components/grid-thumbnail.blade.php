@props([
    'grid' => [],
    'width' => 0,
    'height' => 0,
    'cellSize' => 8,
    'maxWidth' => 120,
])

<div
    {{ $attributes->merge(['class' => 'inline-grid gap-px rounded border border-zinc-300 bg-zinc-200 p-px dark:border-zinc-600 dark:bg-zinc-600']) }}
    style="grid-template-columns: repeat({{ $width }}, minmax(0, 1fr)); width: {{ min($width * $cellSize, $maxWidth) }}px;"
>
    @for($row = 0; $row < $height; $row++)
        @for($col = 0; $col < $width; $col++)
            @php($cell = $grid[$row][$col] ?? null)
            <div class="{{ $cell === null ? 'invisible' : ($cell === '#' ? 'bg-zinc-800 dark:bg-zinc-300' : 'bg-white dark:bg-zinc-800') }}" style="aspect-ratio: 1;"></div>
        @endfor
    @endfor
</div>
