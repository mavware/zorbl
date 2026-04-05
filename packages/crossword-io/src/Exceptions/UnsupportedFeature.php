<?php

namespace Zorbl\CrosswordIO\Exceptions;

enum UnsupportedFeature: string
{
    case VoidCells = 'void_cells';
    case Bars = 'bars';
    case NonAscii = 'non_ascii';

    public function label(): string
    {
        return match ($this) {
            self::VoidCells => 'Void cells will be converted to blocks, changing the grid shape',
            self::Bars => 'Bar-style word boundaries will be removed',
            self::NonAscii => 'Non-ASCII characters in the solution or clues may be lost',
        };
    }
}
