{{--
    Renders a schematic SVG preview of a CrosswordLayout case (or an "auto"
    placeholder when $case is null). Rectangles represent the grid and clue
    panels in their approximate on-screen positions.

    Region kinds:
      - 'grid'         Dark filled block with faint internal grid lines
      - 'panel'        Lighter block with a centred letter label ('A' or 'D')
      - 'panel-both'   Lighter block labeled with both A/D (stacked)
      - 'panel-tabbed' Lighter block with a tab indicator bar on top
      - 'panel-drawer' Narrow edge-slice panel suggesting a drawer

    @var \App\Enums\CrosswordLayout|null $case
--}}
@php
    use App\Enums\CrosswordLayout;

    $case ??= null;

    $regions = match ($case) {
        null => [
            // Auto placeholder — a simple Across|Grid|Down schematic with an "auto" feel.
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 22, 'label' => 'A'],
            ['kind' => 'grid', 'x' => 10, 'y' => 1, 'w' => 12, 'h' => 22],
            ['kind' => 'panel', 'x' => 23, 'y' => 1, 'w' => 8, 'h' => 22, 'label' => 'D'],
        ],
        CrosswordLayout::GridCenterCluesStacked => [
            ['kind' => 'panel', 'x' => 8, 'y' => 1, 'w' => 16, 'h' => 4, 'label' => 'A'],
            ['kind' => 'grid', 'x' => 10, 'y' => 6, 'w' => 12, 'h' => 12],
            ['kind' => 'panel', 'x' => 8, 'y' => 19, 'w' => 16, 'h' => 4, 'label' => 'D'],
        ],
        CrosswordLayout::AcrossLeftDownRight => [
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 22, 'label' => 'A'],
            ['kind' => 'grid', 'x' => 10, 'y' => 1, 'w' => 12, 'h' => 22],
            ['kind' => 'panel', 'x' => 23, 'y' => 1, 'w' => 8, 'h' => 22, 'label' => 'D'],
        ],
        CrosswordLayout::AcrossRightDownLeft => [
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 22, 'label' => 'D'],
            ['kind' => 'grid', 'x' => 10, 'y' => 1, 'w' => 12, 'h' => 22],
            ['kind' => 'panel', 'x' => 23, 'y' => 1, 'w' => 8, 'h' => 22, 'label' => 'A'],
        ],
        CrosswordLayout::AcrossTopDownBottom => [
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 5, 'label' => 'A'],
            ['kind' => 'grid', 'x' => 1, 'y' => 7, 'w' => 30, 'h' => 10],
            ['kind' => 'panel', 'x' => 1, 'y' => 18, 'w' => 30, 'h' => 5, 'label' => 'D'],
        ],
        CrosswordLayout::CluesTop => [
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 14.5, 'h' => 7, 'label' => 'A'],
            ['kind' => 'panel', 'x' => 16.5, 'y' => 1, 'w' => 14.5, 'h' => 7, 'label' => 'D'],
            ['kind' => 'grid', 'x' => 1, 'y' => 9, 'w' => 30, 'h' => 14],
        ],
        CrosswordLayout::CluesBottom => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 14],
            ['kind' => 'panel', 'x' => 1, 'y' => 16, 'w' => 14.5, 'h' => 7, 'label' => 'A'],
            ['kind' => 'panel', 'x' => 16.5, 'y' => 16, 'w' => 14.5, 'h' => 7, 'label' => 'D'],
        ],
        CrosswordLayout::CluesLeft => [
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 10.5, 'label' => 'A'],
            ['kind' => 'panel', 'x' => 1, 'y' => 12.5, 'w' => 8, 'h' => 10.5, 'label' => 'D'],
            ['kind' => 'grid', 'x' => 10, 'y' => 1, 'w' => 21, 'h' => 22],
        ],
        CrosswordLayout::CluesRight => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 21, 'h' => 22],
            ['kind' => 'panel', 'x' => 23, 'y' => 1, 'w' => 8, 'h' => 10.5, 'label' => 'A'],
            ['kind' => 'panel', 'x' => 23, 'y' => 12.5, 'w' => 8, 'h' => 10.5, 'label' => 'D'],
        ],
        CrosswordLayout::CluesLeftSideBySide => [
            ['kind' => 'panel', 'x' => 1, 'y' => 1, 'w' => 7, 'h' => 22, 'label' => 'A'],
            ['kind' => 'panel', 'x' => 9, 'y' => 1, 'w' => 7, 'h' => 22, 'label' => 'D'],
            ['kind' => 'grid', 'x' => 17, 'y' => 1, 'w' => 14, 'h' => 22],
        ],
        CrosswordLayout::CluesRightSideBySide => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 14, 'h' => 22],
            ['kind' => 'panel', 'x' => 16, 'y' => 1, 'w' => 7, 'h' => 22, 'label' => 'A'],
            ['kind' => 'panel', 'x' => 24, 'y' => 1, 'w' => 7, 'h' => 22, 'label' => 'D'],
        ],
        CrosswordLayout::TabbedCluesTop => [
            ['kind' => 'panel-tabbed', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 7],
            ['kind' => 'grid', 'x' => 1, 'y' => 9, 'w' => 30, 'h' => 14],
        ],
        CrosswordLayout::TabbedCluesBottom => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 14],
            ['kind' => 'panel-tabbed', 'x' => 1, 'y' => 16, 'w' => 30, 'h' => 7],
        ],
        CrosswordLayout::TabbedCluesLeft => [
            ['kind' => 'panel-tabbed', 'x' => 1, 'y' => 1, 'w' => 9, 'h' => 22],
            ['kind' => 'grid', 'x' => 11, 'y' => 1, 'w' => 20, 'h' => 22],
        ],
        CrosswordLayout::TabbedCluesRight => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 20, 'h' => 22],
            ['kind' => 'panel-tabbed', 'x' => 22, 'y' => 1, 'w' => 9, 'h' => 22],
        ],
        CrosswordLayout::CluesOverlay => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 22],
            ['kind' => 'panel-overlay', 'x' => 18, 'y' => 4, 'w' => 12, 'h' => 14],
        ],
        CrosswordLayout::CluesDrawerLeft => [
            ['kind' => 'panel-drawer', 'x' => 1, 'y' => 1, 'w' => 3, 'h' => 22],
            ['kind' => 'grid', 'x' => 5, 'y' => 1, 'w' => 26, 'h' => 22],
        ],
        CrosswordLayout::CluesDrawerRight => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 26, 'h' => 22],
            ['kind' => 'panel-drawer', 'x' => 28, 'y' => 1, 'w' => 3, 'h' => 22],
        ],
        CrosswordLayout::CluesDrawerBottom => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 19],
            ['kind' => 'panel-drawer', 'x' => 1, 'y' => 20, 'w' => 30, 'h' => 3],
        ],
        CrosswordLayout::SplitGridLeftAcrossRightDown => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 20, 'h' => 22],
            ['kind' => 'panel-boxed', 'x' => 22, 'y' => 1, 'w' => 9, 'h' => 10.5, 'label' => 'A'],
            ['kind' => 'panel-boxed', 'x' => 22, 'y' => 12.5, 'w' => 9, 'h' => 10.5, 'label' => 'D'],
        ],
        CrosswordLayout::SplitGridTopAcrossBottomDown => [
            ['kind' => 'grid', 'x' => 1, 'y' => 1, 'w' => 30, 'h' => 14],
            ['kind' => 'panel-boxed', 'x' => 1, 'y' => 16, 'w' => 14.5, 'h' => 7, 'label' => 'A'],
            ['kind' => 'panel-boxed', 'x' => 16.5, 'y' => 16, 'w' => 14.5, 'h' => 7, 'label' => 'D'],
        ],
    };
@endphp
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 24" class="h-auto w-full" aria-hidden="true">
    <rect x="0" y="0" width="32" height="24" rx="1" class="fill-zinc-50 dark:fill-zinc-800"/>
    @foreach ($regions as $r)
        @switch ($r['kind'])
            @case('grid')
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}" rx="0.5" class="fill-zinc-700 dark:fill-zinc-300"/>
                @break
            @case('panel')
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}" rx="0.5" class="fill-zinc-300 dark:fill-zinc-600"/>
                @if (! empty($r['label']))
                    <text x="{{ $r['x'] + $r['w'] / 2 }}" y="{{ $r['y'] + $r['h'] / 2 + 1.2 }}" text-anchor="middle" font-size="3.5" font-weight="700" class="fill-zinc-700 dark:fill-zinc-200" font-family="ui-sans-serif, system-ui">{{ $r['label'] }}</text>
                @endif
                @break
            @case('panel-boxed')
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}" rx="1" class="fill-zinc-200 stroke-zinc-500 dark:fill-zinc-700 dark:stroke-zinc-400" stroke-width="0.4"/>
                @if (! empty($r['label']))
                    <text x="{{ $r['x'] + $r['w'] / 2 }}" y="{{ $r['y'] + $r['h'] / 2 + 1.2 }}" text-anchor="middle" font-size="3.5" font-weight="700" class="fill-zinc-700 dark:fill-zinc-200" font-family="ui-sans-serif, system-ui">{{ $r['label'] }}</text>
                @endif
                @break
            @case('panel-tabbed')
                {{-- Tab indicator strip at top --}}
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="2" class="fill-zinc-500 dark:fill-zinc-400"/>
                <rect x="{{ $r['x'] + 0.5 }}" y="{{ $r['y'] + 0.3 }}" width="{{ min(4, $r['w'] / 3) }}" height="1.4" rx="0.3" class="fill-zinc-200 dark:fill-zinc-700"/>
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] + 2 }}" width="{{ $r['w'] }}" height="{{ $r['h'] - 2 }}" rx="0.5" class="fill-zinc-300 dark:fill-zinc-600"/>
                @break
            @case('panel-overlay')
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}" rx="1" class="fill-zinc-100/90 stroke-zinc-500 dark:fill-zinc-800/90 dark:stroke-zinc-400" stroke-width="0.4"/>
                <text x="{{ $r['x'] + $r['w'] / 2 }}" y="{{ $r['y'] + $r['h'] / 2 + 1.2 }}" text-anchor="middle" font-size="3.5" font-weight="700" class="fill-zinc-600 dark:fill-zinc-300" font-family="ui-sans-serif, system-ui">A/D</text>
                @break
            @case('panel-drawer')
                <rect x="{{ $r['x'] }}" y="{{ $r['y'] }}" width="{{ $r['w'] }}" height="{{ $r['h'] }}" rx="0.4" class="fill-zinc-400 dark:fill-zinc-500"/>
                @break
        @endswitch
    @endforeach
</svg>
