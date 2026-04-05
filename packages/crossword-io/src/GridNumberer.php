<?php

namespace Zorbl\CrosswordIO;

class GridNumberer
{
    /**
     * Number the grid cells and compute clue slots.
     *
     * Bars (stored in styles as e.g. {"0,2": {"bars": ["right","bottom"]}}) act
     * as word boundaries alongside black squares and void cells.
     *
     * @param  array<int, array<int, mixed>>  $grid
     * @param  array<string, array{bars?: list<string>, shapebg?: string}>  $styles
     * @return array{
     *     grid: array<int, array<int, mixed>>,
     *     across: array<int, array{number: int, row: int, col: int, length: int}>,
     *     down: array<int, array{number: int, row: int, col: int, length: int}>
     * }
     */
    public function number(array $grid, int $width, int $height, array $styles = [], int $minLength = 2): array
    {
        $numbered = $grid;
        $across = [];
        $down = [];
        $clueNumber = 0;

        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                if ($grid[$row][$col] === null) {
                    $numbered[$row][$col] = null;

                    continue;
                }

                if ($this->isBlock($grid, $row, $col)) {
                    $numbered[$row][$col] = '#';

                    continue;
                }

                $startsAcross = $this->startsAcross($grid, $row, $col, $width, $styles);
                $startsDown = $this->startsDown($grid, $row, $col, $height, $styles);

                $acrossLen = $startsAcross ? $this->wordLength($grid, $row, $col, $width, 'across', $styles) : 0;
                $downLen = $startsDown ? $this->wordLength($grid, $row, $col, $height, 'down', $styles) : 0;

                $hasAcross = $startsAcross && $acrossLen >= $minLength;
                $hasDown = $startsDown && $downLen >= $minLength;

                if ($hasAcross || $hasDown) {
                    $clueNumber++;
                    $numbered[$row][$col] = $clueNumber;

                    if ($hasAcross) {
                        $across[] = [
                            'number' => $clueNumber,
                            'row' => $row,
                            'col' => $col,
                            'length' => $acrossLen,
                        ];
                    }

                    if ($hasDown) {
                        $down[] = [
                            'number' => $clueNumber,
                            'row' => $row,
                            'col' => $col,
                            'length' => $downLen,
                        ];
                    }
                } else {
                    $numbered[$row][$col] = 0;
                }
            }
        }

        return [
            'grid' => $numbered,
            'across' => $across,
            'down' => $down,
        ];
    }

    private function isBlock(array $grid, int $row, int $col): bool
    {
        $cell = $grid[$row][$col] ?? null;

        return $cell === '#' || $cell === null;
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function hasBar(array $styles, int $row, int $col, string $edge): bool
    {
        $key = $row.','.$col;

        return in_array($edge, $styles[$key]['bars'] ?? [], true);
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function hasLeftBoundary(array $grid, int $row, int $col, array $styles): bool
    {
        if ($col === 0) {
            return true;
        }

        if ($this->isBlock($grid, $row, $col - 1)) {
            return true;
        }

        return $this->hasBar($styles, $row, $col, 'left')
            || $this->hasBar($styles, $row, $col - 1, 'right');
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function hasTopBoundary(array $grid, int $row, int $col, array $styles): bool
    {
        if ($row === 0) {
            return true;
        }

        if ($this->isBlock($grid, $row - 1, $col)) {
            return true;
        }

        return $this->hasBar($styles, $row, $col, 'top')
            || $this->hasBar($styles, $row - 1, $col, 'bottom');
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function hasRightBoundary(array $grid, int $row, int $col, int $width, array $styles): bool
    {
        if ($col + 1 >= $width) {
            return true;
        }

        if ($this->isBlock($grid, $row, $col + 1)) {
            return true;
        }

        return $this->hasBar($styles, $row, $col, 'right')
            || $this->hasBar($styles, $row, $col + 1, 'left');
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function hasBottomBoundary(array $grid, int $row, int $col, int $height, array $styles): bool
    {
        if ($row + 1 >= $height) {
            return true;
        }

        if ($this->isBlock($grid, $row + 1, $col)) {
            return true;
        }

        return $this->hasBar($styles, $row, $col, 'bottom')
            || $this->hasBar($styles, $row + 1, $col, 'top');
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function startsAcross(array $grid, int $row, int $col, int $width, array $styles): bool
    {
        $leftBoundary = $this->hasLeftBoundary($grid, $row, $col, $styles);
        $rightOpen = ! $this->hasRightBoundary($grid, $row, $col, $width, $styles);

        return $leftBoundary && $rightOpen;
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function startsDown(array $grid, int $row, int $col, int $height, array $styles): bool
    {
        $topBoundary = $this->hasTopBoundary($grid, $row, $col, $styles);
        $bottomOpen = ! $this->hasBottomBoundary($grid, $row, $col, $height, $styles);

        return $topBoundary && $bottomOpen;
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function wordLength(array $grid, int $row, int $col, int $max, string $direction, array $styles): int
    {
        $length = 1;

        if ($direction === 'across') {
            while (! $this->hasRightBoundary($grid, $row, $col + $length - 1, $max, $styles)) {
                $length++;
            }
        } else {
            while (! $this->hasBottomBoundary($grid, $row + $length - 1, $col, $max, $styles)) {
                $length++;
            }
        }

        return $length;
    }
}
