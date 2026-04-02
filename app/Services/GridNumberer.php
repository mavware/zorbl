<?php

namespace App\Services;

class GridNumberer
{
    /**
     * Number the grid cells and compute clue slots.
     *
     * @param  array<int, array<int, mixed>>  $grid
     * @return array{
     *     grid: array<int, array<int, mixed>>,
     *     across: array<int, array{number: int, row: int, col: int, length: int}>,
     *     down: array<int, array{number: int, row: int, col: int, length: int}>
     * }
     */
    public function number(array $grid, int $width, int $height): array
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

                $startsAcross = $this->startsAcross($grid, $row, $col, $width);
                $startsDown = $this->startsDown($grid, $row, $col, $height);

                if ($startsAcross || $startsDown) {
                    $clueNumber++;
                    $numbered[$row][$col] = $clueNumber;

                    if ($startsAcross) {
                        $across[] = [
                            'number' => $clueNumber,
                            'row' => $row,
                            'col' => $col,
                            'length' => $this->wordLength($grid, $row, $col, $width, 'across'),
                        ];
                    }

                    if ($startsDown) {
                        $down[] = [
                            'number' => $clueNumber,
                            'row' => $row,
                            'col' => $col,
                            'length' => $this->wordLength($grid, $row, $col, $height, 'down'),
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

    /**
     * Check if a cell is impassable (block or void).
     * Both '#' blocks and null (void) cells act as word boundaries.
     */
    private function isBlock(array $grid, int $row, int $col): bool
    {
        $cell = $grid[$row][$col] ?? null;

        return $cell === '#' || $cell === null;
    }

    private function startsAcross(array $grid, int $row, int $col, int $width): bool
    {
        $leftIsBlock = $col === 0 || $this->isBlock($grid, $row, $col - 1);
        $hasRightNeighbor = $col + 1 < $width && ! $this->isBlock($grid, $row, $col + 1);

        return $leftIsBlock && $hasRightNeighbor;
    }

    private function startsDown(array $grid, int $row, int $col, int $height): bool
    {
        $topIsBlock = $row === 0 || $this->isBlock($grid, $row - 1, $col);
        $hasBottomNeighbor = $row + 1 < $height && ! $this->isBlock($grid, $row + 1, $col);

        return $topIsBlock && $hasBottomNeighbor;
    }

    private function wordLength(array $grid, int $row, int $col, int $max, string $direction): int
    {
        $length = 0;

        if ($direction === 'across') {
            while ($col + $length < $max && ! $this->isBlock($grid, $row, $col + $length)) {
                $length++;
            }
        } else {
            while ($row + $length < $max && ! $this->isBlock($grid, $row + $length, $col)) {
                $length++;
            }
        }

        return $length;
    }
}
