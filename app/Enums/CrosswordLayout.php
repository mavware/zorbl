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
     * The Blade partial that renders this layout. Cases without a dedicated
     * view fall back to the automatic layout.
     */
    public function partial(): string
    {
        return match ($this) {
            self::CluesBottom => 'partials.layouts.clues-bottom',
            default => 'partials.layouts.auto',
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
