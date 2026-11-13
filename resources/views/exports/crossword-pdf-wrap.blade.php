{{-- PDF where the clues flow through three columns around a grid in one corner of the page.
     Every position, line break and column comes from $plan (see App\Services\PdfWrapLayout);
     lines are never left to dompdf to wrap, so the heights the plan assumed are the heights drawn. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: letter {{ $orientation }};
            margin: 0.75in;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .page {
            position: relative;
            width: {{ $plan['pageWidth'] }}pt;
        }

        .page-break {
            page-break-before: always;
        }

        .placed {
            position: absolute;
        }

        .lines {
            white-space: nowrap;
            margin: 0;
            padding: 0;
        }

        @include('exports.partials.pdf-grid-styles')

        @include('exports.partials.pdf-meta-answer-styles')

        .title {
            font-size: 18pt;
            font-weight: bold;
        }

        .meta {
            font-size: 10pt;
            color: #333;
        }

        .notes {
            font-size: 9pt;
            font-style: italic;
            color: #333;
        }

        .narrative {
            font-size: 10pt;
            color: #222;
            padding-left: 12pt;
            border-left: 2.5pt solid #999;
        }

        .pdf-image {
            text-align: center;
        }

        .clues-heading {
            font-size: {{ $plan['headingFontSize'] }}pt;
            line-height: {{ round($plan['headingFontSize'] * 1.25, 2) }}pt;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1pt solid #000;
            padding: 0 0 2pt 0;
            margin: 0 0 5pt 0;
        }

        .clue-item {
            font-size: {{ $plan['fontSize'] }}pt;
            line-height: {{ $plan['lineHeight'] }}pt;
            margin: 0 0 {{ $plan['clueSpacing'] }}pt 0;
        }

        .clue-number {
            font-weight: bold;
        }

        .section-label {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            margin: 0 0 12pt 0;
            color: #333;
        }

        .grid-wrapper {
            text-align: center;
        }
    </style>
</head>
<body>
    @foreach ($plan['pages'] as $page)
        <div class="page @unless ($loop->first) page-break @endunless">
            @if ($page['showsHeader'])
                <div class="placed" style="top: 0; left: 0; width: {{ $plan['pageWidth'] }}pt;">
                    @foreach ($plan['header'] as $block)
                        <div style="height: {{ $block['height'] }}pt;">
                            @if ($block['type'] === 'image')
                                <div class="pdf-image">
                                    <img src="{{ $imageDataUri }}" style="width: {{ $block['imageWidth'] }}pt; height: {{ $block['imageHeight'] }}pt;" />
                                </div>
                            @elseif ($block['type'] !== 'space')
                                <div class="lines {{ $block['type'] }}" style="line-height: {{ $block['lineHeight'] }}pt;">
                                    @foreach ($block['lines'] as $line)@if ($line === '')&nbsp;@else{{ $line }}@endif @unless ($loop->last)<br>@endunless @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($page['grid'])
                <div class="placed" style="top: {{ $page['grid']['top'] }}pt; left: {{ $page['grid']['left'] }}pt; width: {{ $plan['gridWidth'] }}pt;">
                    @include('exports.partials.pdf-grid', ['showSolution' => false])
                </div>
            @endif

            @if ($page['metaAnswer'] ?? null)
                <div class="placed" style="top: {{ $page['metaAnswer']['top'] }}pt; left: {{ $page['metaAnswer']['left'] }}pt; width: {{ $page['metaAnswer']['width'] }}pt;">
                    @if ($page['metaAnswer']['promptLines'] !== [])
                        <div class="lines meta-prompt" style="line-height: {{ $page['metaAnswer']['promptLineHeight'] }}pt; margin-bottom: {{ $page['metaAnswer']['promptSpacing'] }}pt;">
                            @foreach ($page['metaAnswer']['promptLines'] as $line){{ $line }}@unless ($loop->last)<br>@endunless @endforeach
                        </div>
                    @endif
                    @include('exports.partials.pdf-meta-answer-blanks', ['lines' => $page['metaAnswer']['blankLines'], 'width' => $page['metaAnswer']['width']])
                </div>
            @endif

            @foreach ($page['regions'] as $region)
                <div class="placed" style="top: {{ $region['top'] }}pt; left: {{ $region['left'] }}pt; width: {{ $region['width'] }}pt;">
                    @foreach ($region['items'] as $item)
                        @if ($item['type'] === 'heading')
                            <div class="lines clues-heading" style="margin-top: {{ $item['marginTop'] }}pt;">{{ $item['lines'][0] }}</div>
                        @else
                            {{-- Kept on one line: stray whitespace would add width to lines the plan measured exactly. --}}
                            <div class="lines clue-item"><span class="clue-number">{{ $item['number'] }}&nbsp;</span>@foreach ($item['lines'] as $line){{ $line }}@unless ($loop->last)<br>@endunless @endforeach</div>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach

    @if ($includeSolution)
        <div class="page-break"></div>

        <p class="section-label">Solution</p>

        <div class="grid-wrapper">
            @include('exports.partials.pdf-grid', ['showSolution' => true])
        </div>
    @endif
</body>
</html>
