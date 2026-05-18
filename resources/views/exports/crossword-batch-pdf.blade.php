<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter {{ $orientation ?? 'portrait' }};
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

        .cover-page {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding-top: 3in;
        }

        .cover-title {
            font-size: 28pt;
            font-weight: bold;
            margin: 0 0 12pt 0;
        }

        .cover-count {
            font-size: 14pt;
            color: #555;
            margin: 0;
        }

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

        .header .notes {
            font-size: 9pt;
            font-style: italic;
            color: #333;
            margin: 4pt 0 0 0;
        }

        .grid-wrapper {
            text-align: center;
        }

        .grid-table {
            border-collapse: collapse;
            margin: 0 auto;
        }

        .grid-table td.block {
            background-color: #000;
            border-color: #000;
        }

        .grid-table td.void {
            background: none;
            border: none;
        }

        .grid-table td.bar-top { border-top: 2.5pt solid #000; }
        .grid-table td.bar-right { border-right: 2.5pt solid #000; }
        .grid-table td.bar-bottom { border-bottom: 2.5pt solid #000; }
        .grid-table td.bar-left { border-left: 2.5pt solid #000; }

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

        .custom-page {
            padding-top: 1in;
        }

        .custom-page-heading {
            font-size: 22pt;
            font-weight: bold;
            margin: 0 0 16pt 0;
            text-align: center;
        }

        .custom-page-body {
            font-size: 12pt;
            line-height: 1.6;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    @php
        $hasContentBefore = false;
        $beforePages ??= [];
        $afterPages ??= [];
    @endphp

    @if ($collectionTitle)
        @php $hasContentBefore = true; @endphp
        <div class="cover-page">
            <div>
                <p class="cover-title">{{ $collectionTitle }}</p>
                <p class="cover-count">{{ trans_choice(':count puzzle|:count puzzles', count($puzzles)) }}</p>
            </div>
        </div>
    @endif

    @foreach ($beforePages as $customPage)
        @if ($hasContentBefore)
            <div class="page-break"></div>
        @endif
        @php $hasContentBefore = true; @endphp
        <div class="custom-page">
            @if (!empty($customPage['heading']))
                <p class="custom-page-heading">{{ $customPage['heading'] }}</p>
            @endif
            @if (!empty($customPage['body']))
                <div class="custom-page-body">{{ $customPage['body'] }}</div>
            @endif
        </div>
    @endforeach

    @foreach ($puzzles as $index => $puzzle)
        @if ($hasContentBefore || $index > 0)
            <div class="page-break"></div>
        @endif

        <div class="header">
            <p class="title">{{ $puzzle['title'] }}</p>
            @if ($puzzle['author'])
                <p class="meta">By {{ $puzzle['author'] }}</p>
            @endif
            @if ($puzzle['copyright'])
                <p class="meta">&copy; {{ $puzzle['copyright'] }}</p>
            @endif
            @if ($puzzle['notes'] ?? null)
                <p class="notes">{{ $puzzle['notes'] }}</p>
            @endif
        </div>

        <style>
            .grid-table-{{ $index }} td {
                width: {{ $puzzle['cellSize'] }}in;
                height: {{ $puzzle['cellSize'] }}in;
                padding: 0;
                text-align: center;
                vertical-align: middle;
                border: 1pt solid #000;
                position: relative;
            }
            .grid-table-{{ $index }} .cell-number {
                font-size: {{ $puzzle['numberFontSize'] }}pt;
                font-weight: bold;
                text-align: left;
                line-height: 1;
                padding-left: 1pt;
                padding-top: 0;
                margin: 0;
                height: {{ $puzzle['numberHeight'] }}in;
            }
            .grid-table-{{ $index }} .cell-letter {
                font-size: {{ $puzzle['letterFontSize'] }}pt;
                font-weight: normal;
                text-align: center;
                line-height: 1;
                margin: 0;
                padding: 0;
            }
            .grid-table-{{ $index }} .cell-letter.prefilled {
                color: #555;
            }
            .grid-table-{{ $index }} .circle-indicator {
                width: {{ round($puzzle['cellSize'] * 0.82, 3) }}in;
                height: {{ round($puzzle['cellSize'] * 0.82, 3) }}in;
                border: 0.7pt solid #555;
                border-radius: 50%;
                margin: 0 auto;
            }
        </style>

        <div class="grid-wrapper">
            <table class="grid-table grid-table-{{ $index }}">
                @foreach ($puzzle['numberedGrid'] as $r => $row)
                    <tr>
                        @foreach ($row as $c => $cell)
                            @if ($cell === null)
                                <td class="void"></td>
                            @elseif ($cell === '#')
                                <td class="block"></td>
                            @else
                                @php
                                    $styleKey = $r . ',' . $c;
                                    $cellStyle = $puzzle['styles'][$styleKey] ?? [];
                                    $classes = [];
                                    $inlineStyle = '';
                                    $hasCircle = !empty($cellStyle['shapebg']) && $cellStyle['shapebg'] === 'circle';
                                    if (!empty($cellStyle['color'])) {
                                        $inlineStyle = 'background-color: ' . e($cellStyle['color']) . ';';
                                    }
                                    foreach ($cellStyle['bars'] ?? [] as $bar) {
                                        $classes[] = 'bar-' . $bar;
                                    }
                                    $prefilledLetter = $puzzle['prefilled'][$r][$c] ?? '';
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

        @if ($puzzle['forceCluePageBreak'] ?? false)
            <div class="page-break"></div>
        @endif

        @if (!empty($puzzle['cluesAcross']))
            <div class="clues-section">
                <p class="clues-heading">Across</p>
                <div class="clues-columns">
                    @foreach ($puzzle['cluesAcross'] as $clue)
                        <p class="clue-item">
                            <span class="clue-number">{{ $clue['number'] }}.</span>
                            {{ $clue['clue'] ?? '' }}
                        </p>
                    @endforeach
                </div>
            </div>
        @endif

        @if (!empty($puzzle['cluesDown']))
            <div class="clues-section">
                <p class="clues-heading">Down</p>
                <div class="clues-columns">
                    @foreach ($puzzle['cluesDown'] as $clue)
                        <p class="clue-item">
                            <span class="clue-number">{{ $clue['number'] }}.</span>
                            {{ $clue['clue'] ?? '' }}
                        </p>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    @foreach ($afterPages as $customPage)
        <div class="page-break"></div>
        <div class="custom-page">
            @if (!empty($customPage['heading']))
                <p class="custom-page-heading">{{ $customPage['heading'] }}</p>
            @endif
            @if (!empty($customPage['body']))
                <div class="custom-page-body">{{ $customPage['body'] }}</div>
            @endif
        </div>
    @endforeach
</body>
</html>
