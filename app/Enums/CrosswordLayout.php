<?php

namespace App\Enums;

enum CrosswordLayout: int
{
    case GridCenterCluesStacked = 1;
    case GridCenterCluesSideBySide = 2;

    case CluesTop = 10;
    case CluesBottom = 11;
    case CluesLeft = 12;
    case CluesRight = 13;

    case TabbedCluesTop = 20;
    case TabbedCluesBottom = 21;
    case TabbedCluesLeft = 22;
    case TabbedCluesRight = 23;

    case GridLeftCluesRight = 30;
    case GridRightCluesLeft = 31;
    case GridTopCluesBottom = 32;
    case GridBottomCluesTop = 33;

    case AcrossLeftDownRight = 40;
    case AcrossRightDownLeft = 41;
    case AcrossTopDownBottom = 42;

    case CluesOverlay = 50;
    case CluesDrawerLeft = 51;
    case CluesDrawerRight = 52;
    case CluesDrawerBottom = 53;

    case SplitGridLeftAcrossRightDown = 60;
    case SplitGridTopAcrossBottomDown = 61;

    /**
     * The case used when the user hasn't picked a layout. Side-by-side
     * Across|Grid|Down works well for standard grids; once the grid gets too
     * wide to afford both 256 px side panels comfortably, we stack the clues
     * in a single column on the right instead.
     */
    public static function auto(int $width): self
    {
        return $width > 17 ? self::CluesRight : self::AcrossLeftDownRight;
    }

    /**
     * The Blade partial that renders this layout.
     */
    public function partial(): string
    {
        return match ($this) {
            self::GridCenterCluesStacked => 'partials.layouts.grid-center-clues-stacked',
            self::GridCenterCluesSideBySide => 'partials.layouts.grid-center-clues-side-by-side',

            self::CluesTop => 'partials.layouts.clues-top',
            self::CluesBottom => 'partials.layouts.clues-bottom',
            self::CluesLeft => 'partials.layouts.clues-left',
            self::CluesRight => 'partials.layouts.clues-right',

            self::TabbedCluesTop => 'partials.layouts.tabbed-clues-top',
            self::TabbedCluesBottom => 'partials.layouts.tabbed-clues-bottom',
            self::TabbedCluesLeft => 'partials.layouts.tabbed-clues-left',
            self::TabbedCluesRight => 'partials.layouts.tabbed-clues-right',

            self::GridLeftCluesRight => 'partials.layouts.grid-left-clues-right',
            self::GridRightCluesLeft => 'partials.layouts.grid-right-clues-left',
            self::GridTopCluesBottom => 'partials.layouts.grid-top-clues-bottom',
            self::GridBottomCluesTop => 'partials.layouts.grid-bottom-clues-top',

            self::AcrossLeftDownRight => 'partials.layouts.across-left-down-right',
            self::AcrossRightDownLeft => 'partials.layouts.across-right-down-left',
            self::AcrossTopDownBottom => 'partials.layouts.across-top-down-bottom',

            self::CluesOverlay => 'partials.layouts.clues-overlay',
            self::CluesDrawerLeft => 'partials.layouts.clues-drawer-left',
            self::CluesDrawerRight => 'partials.layouts.clues-drawer-right',
            self::CluesDrawerBottom => 'partials.layouts.clues-drawer-bottom',

            self::SplitGridLeftAcrossRightDown => 'partials.layouts.split-grid-left-across-right-down',
            self::SplitGridTopAcrossBottomDown => 'partials.layouts.split-grid-top-across-bottom-down',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GridCenterCluesStacked => 'Grid centered, clues stacked above and below',
            self::GridCenterCluesSideBySide => 'Grid centered, clues on both sides',

            self::CluesTop => 'Clues above grid',
            self::CluesBottom => 'Clues below grid',
            self::CluesLeft => 'Clues left of grid',
            self::CluesRight => 'Clues right of grid',

            self::TabbedCluesTop => 'Tabbed clues above grid',
            self::TabbedCluesBottom => 'Tabbed clues below grid',
            self::TabbedCluesLeft => 'Tabbed clues left of grid',
            self::TabbedCluesRight => 'Tabbed clues right of grid',

            self::GridLeftCluesRight => 'Grid left, clues right',
            self::GridRightCluesLeft => 'Grid right, clues left',
            self::GridTopCluesBottom => 'Grid top, clues bottom',
            self::GridBottomCluesTop => 'Grid bottom, clues top',

            self::AcrossLeftDownRight => 'Across clues left, down clues right',
            self::AcrossRightDownLeft => 'Across clues right, down clues left',
            self::AcrossTopDownBottom => 'Across clues top, down clues bottom',

            self::CluesOverlay => 'Clues overlay on grid',
            self::CluesDrawerLeft => 'Clues in slide-out drawer (left)',
            self::CluesDrawerRight => 'Clues in slide-out drawer (right)',
            self::CluesDrawerBottom => 'Clues in slide-out drawer (bottom)',

            self::SplitGridLeftAcrossRightDown => 'Split view: grid left, across top-right, down bottom-right',
            self::SplitGridTopAcrossBottomDown => 'Split view: grid top, across bottom-left, down bottom-right',
        };
    }
}
