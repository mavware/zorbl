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

        @include('exports.partials.pdf-grid-styles')

        @include('exports.partials.pdf-meta-answer-styles')

        .meta-answer {
            margin-top: 12pt;
        }

        .meta-answer .meta-prompt {
            margin: 0 0 8pt 0;
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

        .narrative {
            font-size: 10pt;
            line-height: 1.5;
            color: #222;
            margin: 0 0 14pt 0;
            padding: 8pt 12pt;
            border-left: 2.5pt solid #999;
            white-space: pre-wrap;
        }

        .pdf-image {
            text-align: center;
            margin: 0 0 14pt 0;
        }

        .pdf-image img {
            max-width: 100%;
            max-height: 2.5in;
        }
    </style>
</head>
<body>
    {{-- Page 1: Blank numbered grid --}}
    <div class="header">
        <p class="title">{{ $title }}</p>
        @if (filled($author))
            <p class="meta">By {{ $author }}</p>
        @endif
        @if (filled($copyright))
            {{-- The editor's default copyright line already starts with the symbol; typed or imported ones may not. --}}
            <p class="meta">@unless (preg_match('/^\s*(©|\(c\)|copyright\b)/iu', $copyright))&copy; @endunless{{ trim($copyright) }}</p>
        @endif
        @if ($notes ?? null)
            <p class="notes">{{ $notes }}</p>
        @endif
    </div>

    @if ($narrative ?? null)
        <div class="narrative">{{ $narrative }}</div>
    @endif

    @if ($imageDataUri ?? null)
        <div class="pdf-image">
            <img src="{{ $imageDataUri }}" />
        </div>
    @endif

    <div class="grid-wrapper">
        @include('exports.partials.pdf-grid', ['showSolution' => false])
    </div>

    {{-- Meta answer: its prompt and a blank per letter, directly under the grid as on the solve page --}}
    @if ($metaAnswer ?? null)
        <div class="meta-answer">
            @if (filled($metaAnswer['prompt']))
                <p class="meta-prompt">{{ $metaAnswer['prompt'] }}</p>
            @endif
            @include('exports.partials.pdf-meta-answer-blanks', ['lines' => $metaAnswer['lines'], 'width' => $metaAnswer['width']])
        </div>
    @endif

    {{-- Clues: separate page for large puzzles, inline for small ones --}}
    @if ($forceCluePageBreak ?? false)
        <div class="page-break"></div>
    @endif

    @if (!empty($cluesAcross))
        <div class="clues-section">
            <p class="clues-heading">Across</p>
            <div class="clues-columns">
                @foreach ($cluesAcross as $clue)
                    <p class="clue-item">
                        <span class="clue-number">{{ $clue['number'] }}.</span>
                        {!! nl2br(e($clue['clue'] ?? '')) !!}
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
                        {!! nl2br(e($clue['clue'] ?? '')) !!}
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
            @include('exports.partials.pdf-grid', ['showSolution' => true])
        </div>
    @endif
</body>
</html>
