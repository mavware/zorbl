{{--
    Renders a schematic SVG preview of a PdfLayout case: a portrait page with
    a title rule, the grid as a dark block and the clues as ruled lines in the
    columns they flow through.

    @var \App\Enums\PdfLayout $case
--}}
@php
    use App\Enums\PdfLayout;

    // The page body runs from y=6 to y=30; wrap-around layouts use three 6-wide columns at x=2, 9 and 16.
    [$grid, $clueBlocks] = match ($case) {
        PdfLayout::Standard => [
            ['x' => 6, 'y' => 6, 'w' => 12, 'h' => 12],
            [
                ['x' => 2, 'y' => 20, 'w' => 9.5, 'h' => 10],
                ['x' => 12.5, 'y' => 20, 'w' => 9.5, 'h' => 10],
            ],
        ],
        PdfLayout::GridTopLeft => [
            ['x' => 2, 'y' => 6, 'w' => 13, 'h' => 13],
            [
                ['x' => 2, 'y' => 21, 'w' => 6, 'h' => 9],
                ['x' => 9, 'y' => 21, 'w' => 6, 'h' => 9],
                ['x' => 16, 'y' => 6, 'w' => 6, 'h' => 24],
            ],
        ],
        PdfLayout::GridTopRight => [
            ['x' => 9, 'y' => 6, 'w' => 13, 'h' => 13],
            [
                ['x' => 2, 'y' => 6, 'w' => 6, 'h' => 24],
                ['x' => 9, 'y' => 21, 'w' => 6, 'h' => 9],
                ['x' => 16, 'y' => 21, 'w' => 6, 'h' => 9],
            ],
        ],
        PdfLayout::GridBottomLeft => [
            ['x' => 2, 'y' => 17, 'w' => 13, 'h' => 13],
            [
                ['x' => 2, 'y' => 6, 'w' => 6, 'h' => 9],
                ['x' => 9, 'y' => 6, 'w' => 6, 'h' => 9],
                ['x' => 16, 'y' => 6, 'w' => 6, 'h' => 24],
            ],
        ],
        PdfLayout::GridBottomRight => [
            ['x' => 9, 'y' => 17, 'w' => 13, 'h' => 13],
            [
                ['x' => 2, 'y' => 6, 'w' => 6, 'h' => 24],
                ['x' => 9, 'y' => 6, 'w' => 6, 'h' => 9],
                ['x' => 16, 'y' => 6, 'w' => 6, 'h' => 9],
            ],
        ],
    };
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 32" class="h-auto w-full" aria-hidden="true">
    <rect x="0" y="0" width="24" height="32" rx="1" class="fill-zinc-50 dark:fill-zinc-800"/>
    <rect x="2" y="2" width="10" height="1.6" rx="0.4" class="fill-zinc-500 dark:fill-zinc-400"/>
    <rect x="{{ $grid['x'] }}" y="{{ $grid['y'] }}" width="{{ $grid['w'] }}" height="{{ $grid['h'] }}" rx="0.5" class="fill-zinc-700 dark:fill-zinc-300"/>
    @foreach ($clueBlocks as $block)
        @for ($lineY = $block['y'] + 0.2; $lineY + 0.7 <= $block['y'] + $block['h']; $lineY += 1.6)
            <rect x="{{ $block['x'] }}" y="{{ $lineY }}" width="{{ $block['w'] }}" height="0.7" rx="0.3" class="fill-zinc-300 dark:fill-zinc-600"/>
        @endfor
    @endforeach
</svg>
