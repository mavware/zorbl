<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter;
            margin: 0.75in;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-before: always;
        }

        /* Header */
        .header {
            margin-bottom: 12pt;
        }

        .header .title {
            font-size: 18pt;
            font-weight: bold;
            margin: 0 0 4pt 0;
        }

        .header .meta {
            font-size: 10pt;
            color: #333;
            margin: 0;
        }

        /* Grid table */
        .grid-table {
            border-collapse: collapse;
            margin: 0 auto;
        }

        .grid-table td {
            width: {{ $cellSize }}in;
            height: {{ $cellSize }}in;
            padding: 0;
            text-align: center;
            vertical-align: middle;
            border: 1pt solid #000;
            position: relative;
        }

        .grid-table td.block {
            background-color: #000;
            border-color: #000;
        }

        .grid-table td.void {
            background: none;
            border: none;
        }

        .circle-indicator {
            width: {{ round($cellSize * 0.82, 3) }}in;
            height: {{ round($cellSize * 0.82, 3) }}in;
            border: 0.7pt solid #555;
            border-radius: 50%;
            margin: 0 auto;
        }

        .grid-table td.bar-top { border-top: 2.5pt solid #000; }
        .grid-table td.bar-right { border-right: 2.5pt solid #000; }
        .grid-table td.bar-bottom { border-bottom: 2.5pt solid #000; }
        .grid-table td.bar-left { border-left: 2.5pt solid #000; }

        .cell-number {
            font-size: {{ $numberFontSize }}pt;
            font-weight: bold;
            text-align: left;
            line-height: 1;
            padding-left: 1pt;
            padding-top: 0;
            margin: 0;
            height: {{ $numberHeight }}in;
        }

        .cell-letter {
            font-size: {{ $letterFontSize }}pt;
            font-weight: normal;
            text-align: center;
            line-height: 1;
            margin: 0;
            padding: 0;
        }

        .cell-letter.prefilled {
            color: #555;
        }

        /* Clues section */
        .clues-section {
            margin-top: 16pt;
        }

        .clues-heading {
            font-size: 12pt;
            font-weight: bold;
            margin: 0 0 6pt 0;
            text-transform: uppercase;
            border-bottom: 1pt solid #000;
            padding-bottom: 2pt;
        }

        .clues-columns {
            column-count: 2;
            column-gap: 24pt;
            font-size: 9pt;
            line-height: 1.4;
        }

        .clue-item {
            margin: 0 0 2pt 0;
            break-inside: avoid;
        }

        .clue-number {
            font-weight: bold;
        }

        .section-label {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 12pt;
            color: #333;
        }

        .header .notes {
            font-size: 9pt;
            font-style: italic;
            color: #333;
            margin: 4pt 0 0 0;
        }

        .grid-wrapper {
            text-align: center;
        }
    </style>
</head>
<body>
    {{-- Page 1: Blank numbered grid --}}
    <div class="header">
        <p class="title">{{ $title }}</p>
        @if ($author)
            <p class="meta">By {{ $author }}</p>
        @endif
        @if ($copyright)
            <p class="meta">&copy; {{ $copyright }}</p>
        @endif
        @if ($notes)
            <p class="notes">{{ $notes }}</p>
        @endif
    </div>

    <div class="grid-wrapper">
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
                                @if (filled($prefilledLetter))
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
    </div>

    {{-- Clues page --}}
    <div class="page-break"></div>

    @if (!empty($cluesAcross))
        <div class="clues-section">
            <p class="clues-heading">Across</p>
            <div class="clues-columns">
                @foreach ($cluesAcross as $clue)
                    <p class="clue-item">
                        <span class="clue-number">{{ $clue['number'] }}.</span>
                        {{ $clue['clue'] ?? '' }}
                    </p>
                @endforeach
            </div>
        </div>
    @endif

    @if (!empty($cluesDown))
        <div class="clues-section">
            <p class="clues-heading">Down</p>
            <div class="clues-columns">
                @foreach ($cluesDown as $clue)
                    <p class="clue-item">
                        <span class="clue-number">{{ $clue['number'] }}.</span>
                        {{ $clue['clue'] ?? '' }}
                    </p>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Optional solution page --}}
    @if ($includeSolution)
        <div class="page-break"></div>

        <p class="section-label">Solution</p>

        <div class="grid-wrapper">
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
                                @endphp
                                <td class="{{ implode(' ', $classes) }}" @if ($inlineStyle) style="{{ $inlineStyle }}" @endif>
                                    @if ($hasCircle)<div class="circle-indicator">@endif
                                    @if (is_int($cell) && $cell > 0)
                                        <div class="cell-number">{{ $cell }}</div>
                                    @else
                                        <div class="cell-number">&nbsp;</div>
                                    @endif
                                    <div class="cell-letter">{{ strtoupper($solution[$r][$c] ?? '') }}</div>
                                    @if ($hasCircle)</div>@endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</body>
</html>
