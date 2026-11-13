<?php

namespace App\Enums;

use Illuminate\Contracts\View\View;

enum PdfLayout: string
{
    /* Declared in the order the layout picker shows them. */
    case GridTopLeft = 'grid-top-left';
    case GridTopRight = 'grid-top-right';
    case GridBottomLeft = 'grid-bottom-left';
    case GridBottomRight = 'grid-bottom-right';
    case Standard = 'standard';

    /**
     * The layout the download modal starts on.
     */
    public static function default(): self
    {
        return self::GridBottomRight;
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Grid above clues',
            self::GridTopLeft => 'Grid upper left',
            self::GridTopRight => 'Grid upper right',
            self::GridBottomLeft => 'Grid lower left',
            self::GridBottomRight => 'Grid lower right',
        };
    }

    /**
     * What the layout does with the clues, for tooltips beside the short label.
     */
    public function description(): string
    {
        return match ($this) {
            self::Standard => 'The grid sits above the clues, which run in two columns below it.',
            self::GridTopLeft => 'Clues start below the grid and finish in the column to its right.',
            self::GridTopRight => 'Clues start in the column left of the grid and continue below it.',
            self::GridBottomLeft => 'Clues start above the grid and finish in the column to its right.',
            self::GridBottomRight => 'Clues start in the column left of the grid and continue above it.',
        };
    }

    /**
     * Render the schematic SVG icon for this case as a Blade view.
     */
    public function icon(): View
    {
        return view('partials.pdf-layout-icon', ['case' => $this]);
    }

    /**
     * Whether the clues flow through three columns around a grid that sits
     * in one corner of the page and spans two of them.
     */
    public function wrapsCluesAroundGrid(): bool
    {
        return $this !== self::Standard;
    }

    public function gridIsOnRight(): bool
    {
        return in_array($this, [self::GridTopRight, self::GridBottomRight], true);
    }

    public function gridIsAtBottom(): bool
    {
        return in_array($this, [self::GridBottomLeft, self::GridBottomRight], true);
    }
}
