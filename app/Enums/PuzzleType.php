<?php

namespace App\Enums;

enum PuzzleType: string
{
    case Standard = 'standard';
    case Diamond = 'diamond';
    case Freestyle = 'freestyle';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Diamond => 'Diamond',
            self::Freestyle => 'Freestyle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'Classic crossword with rotational symmetry. Any dimensions.',
            self::Diamond => 'Diamond-shaped grid with the corners removed. Odd-sized square grid.',
            self::Freestyle => 'No symmetry or shape constraints. Any dimensions.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Standard => 'squares-2x2',
            self::Diamond => 'stop',
            self::Freestyle => 'pencil-square',
        };
    }

    public function requiresSquare(): bool
    {
        return match ($this) {
            self::Diamond => true,
            self::Standard, self::Freestyle => false,
        };
    }

    public function requiresOdd(): bool
    {
        return match ($this) {
            self::Diamond => true,
            default => false,
        };
    }

    /**
     * Generate the initial grid for this puzzle type.
     *
     * @return array<int, array<int, int|string|null>>
     */
    public function generateGrid(int $width, int $height): array
    {
        return match ($this) {
            self::Diamond => self::diamondGrid($width, $height),
            default => array_fill(0, $height, array_fill(0, $width, 0)),
        };
    }

    /**
     * @return array<int, array<int, int|string|null>>
     */
    private static function diamondGrid(int $width, int $height): array
    {
        $grid = [];
        $centerR = intdiv($height, 2);
        $centerC = intdiv($width, 2);

        for ($r = 0; $r < $height; $r++) {
            $row = [];
            for ($c = 0; $c < $width; $c++) {
                $distance = abs($r - $centerR) + abs($c - $centerC);
                $row[] = $distance > $centerR ? null : 0;
            }
            $grid[] = $row;
        }

        return $grid;
    }
}
