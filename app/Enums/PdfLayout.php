<?php

namespace App\Enums;

enum PdfLayout: string
{
    case Standard = 'standard';
    case GridTopLeft = 'grid-top-left';
    case GridTopRight = 'grid-top-right';
    case GridBottomLeft = 'grid-bottom-left';
    case GridBottomRight = 'grid-bottom-right';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Grid above clues',
            self::GridTopLeft => 'Clues wrap around grid, grid in upper left',
            self::GridTopRight => 'Clues wrap around grid, grid in upper right',
            self::GridBottomLeft => 'Clues wrap around grid, grid in lower left',
            self::GridBottomRight => 'Clues wrap around grid, grid in lower right',
        };
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
