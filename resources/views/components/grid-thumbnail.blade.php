@props([
    'grid' => [],
    'styles' => null,
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
            @php
                $cell = $grid[$row][$col] ?? null;
                $bars = is_array($styles) ? ($styles["{$row},{$col}"]['bars'] ?? []) : [];
                $shadows = [];
                if (in_array('top', $bars, true)) {
                    $shadows[] = 'inset 0 1px 0 0 rgb(20 184 166)';
                }
                if (in_array('bottom', $bars, true)) {
                    $shadows[] = 'inset 0 -1px 0 0 rgb(20 184 166)';
                }
                if (in_array('left', $bars, true)) {
                    $shadows[] = 'inset 1px 0 0 0 rgb(20 184 166)';
                }
                if (in_array('right', $bars, true)) {
                    $shadows[] = 'inset -1px 0 0 0 rgb(20 184 166)';
                }
                $shadowStyle = $shadows ? 'box-shadow: '.implode(', ', $shadows).';' : '';
            @endphp
            <div
                class="{{ $cell === null ? 'invisible' : ($cell === '#' ? 'bg-zinc-800 dark:bg-zinc-300' : 'bg-elevated') }}"
                style="aspect-ratio: 1;{{ $shadowStyle }}"
            ></div>
        @endfor
    @endfor
</div>
