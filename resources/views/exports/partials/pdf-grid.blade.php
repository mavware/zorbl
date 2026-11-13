{{-- The puzzle grid as a table. Expects $numberedGrid, $styles, $prefilled, $solution and
     $showSolution: whether cells show the answer instead of only their prefilled letters. --}}
<table class="grid-table">
    @foreach ($numberedGrid as $r => $row)
        <tr>
            @foreach ($row as $c => $cell)
                @if ($cell === null)
                    <td class="void"></td>
                @elseif ($cell === '#')
                    <td class="block"></td>
                @else
                    @php
                        $styleKey = $r . ',' . $c;
                        $cellStyle = $styles[$styleKey] ?? [];
                        $classes = [];
                        $inlineStyle = '';
                        $hasCircle = !empty($cellStyle['shapebg']) && $cellStyle['shapebg'] === 'circle';
                        if (!empty($cellStyle['color'])) {
                            $inlineStyle = 'background-color: ' . e($cellStyle['color']) . ';';
                        }
                        foreach ($cellStyle['bars'] ?? [] as $bar) {
                            $classes[] = 'bar-' . $bar;
                        }
                        $prefilledLetter = $prefilled[$r][$c] ?? '';
                    @endphp
                    <td class="{{ implode(' ', $classes) }}" @if ($inlineStyle) style="{{ $inlineStyle }}" @endif>
                        @if ($hasCircle)<div class="circle-indicator">@endif
                        @if (is_int($cell) && $cell > 0)
                            <div class="cell-number">{{ $cell }}</div>
                        @else
                            <div class="cell-number">&nbsp;</div>
                        @endif
                        @if ($showSolution)
                            <div class="cell-letter">{{ strtoupper($solution[$r][$c] ?? '') }}</div>
                        @elseif (filled($prefilledLetter))
                            <div class="cell-letter prefilled">{{ strtoupper($prefilledLetter) }}</div>
                        @else
                            <div class="cell-letter">&nbsp;</div>
                        @endif
                        @if ($hasCircle)</div>@endif
                    </td>
                @endif
            @endforeach
        </tr>
    @endforeach
</table>
