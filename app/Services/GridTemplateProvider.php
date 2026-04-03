<?php

namespace App\Services;

class GridTemplateProvider
{
    /**
     * Get available grid templates for the given dimensions.
     *
     * @return array<int, array{name: string, grid: array<int, array<int, int|string>>}>
     */
    public function getTemplates(int $width, int $height): array
    {
        $key = "{$width}x{$height}";

        return match ($key) {
            '5x5' => $this->templates5x5(),
            '11x11' => $this->templates11x11(),
            '13x13' => $this->templates13x13(),
            '15x15' => $this->templates15x15(),
            '21x21' => $this->templates21x21(),
            default => [],
        };
    }

    /**
     * Build a grid from block positions.
     *
     * @param  array<int, array{int, int}>  $blocks  Positions of block cells as [row, col] pairs
     * @return array<int, array<int, int|string>>
     */
    private function buildGrid(int $width, int $height, array $blocks): array
    {
        $grid = array_fill(0, $height, array_fill(0, $width, 0));

        foreach ($blocks as [$r, $c]) {
            $grid[$r][$c] = '#';
        }

        return $grid;
    }

    /**
     * Generate symmetric block positions from half the blocks.
     * Each block at (r,c) automatically mirrors to (h-1-r, w-1-c).
     *
     * @param  array<int, array{int, int}>  $halfBlocks  Block positions for one half
     * @return array<int, array{int, int}>
     */
    private function symmetricBlocks(int $width, int $height, array $halfBlocks): array
    {
        $all = [];

        foreach ($halfBlocks as [$r, $c]) {
            $all[] = [$r, $c];
            $mr = $height - 1 - $r;
            $mc = $width - 1 - $c;

            if ($mr !== $r || $mc !== $c) {
                $all[] = [$mr, $mc];
            }
        }

        return $all;
    }

    /**
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates5x5(): array
    {
        // 5x5 #1: single corner block
        // Runs: row 0: 4, row 4: 4, col 0: 4, col 4: 4. All others: 5.
        $corner = $this->symmetricBlocks(5, 5, [[0, 4]]);

        // 5x5 #2: opposite corner block
        // Runs: row 0: 4, row 4: 4, col 0: 4, col 4: 4. All others: 5.
        $opposite = $this->symmetricBlocks(5, 5, [[0, 0]]);

        return [
            ['name' => 'Corner', 'grid' => $this->buildGrid(5, 5, $corner)],
            ['name' => 'Opposite Corner', 'grid' => $this->buildGrid(5, 5, $opposite)],
        ];
    }

    /**
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates11x11(): array
    {
        // 11x11 #1 "Cross": blocks at center cross intersections
        // Row 0/10: 5,5. Row 5: 5,5. Col 0/10: 5,5. Col 5: 5,5.
        $cross = $this->symmetricBlocks(11, 11, [
            [0, 5], [5, 0],
        ]);

        // 11x11 #2 "Grid": 3x3 grid of blocks
        // Row 3/7: 3,3,3. Col 3/7: 3,3,3.
        $grid = $this->symmetricBlocks(11, 11, [
            [3, 3], [3, 7],
        ]);

        return [
            ['name' => 'Cross', 'grid' => $this->buildGrid(11, 11, $cross)],
            ['name' => 'Grid', 'grid' => $this->buildGrid(11, 11, $grid)],
        ];
    }

    /**
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates13x13(): array
    {
        // 13x13 #1 "Cross": center cross
        // Row 0/12: 6,6. Row 6: 6,6. Col 0/12: 6,6. Col 6: 6,6.
        $cross = $this->symmetricBlocks(13, 13, [
            [0, 6], [6, 0],
        ]);

        // 13x13 #2 "Window": blocks at corner thirds
        // Row 0/12: 4,3,4. Row 4/8: 12. Col 0/12: 4,3,4. Col 4/8: 12.
        $window = $this->symmetricBlocks(13, 13, [
            [0, 4], [0, 8], [4, 0], [4, 12],
        ]);

        return [
            ['name' => 'Cross', 'grid' => $this->buildGrid(13, 13, $cross)],
            ['name' => 'Window', 'grid' => $this->buildGrid(13, 13, $window)],
        ];
    }

    /**
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates15x15(): array
    {
        // Pattern 1 - "Wide Open" (12 blocks)
        // Verified: all across/down runs ≥ 3
        $wideOpen = $this->symmetricBlocks(15, 15, [
            [0, 4], [0, 10],
            [4, 0], [4, 7],
            [7, 3],
        ]);

        // Pattern 2 - "Classic" (22 blocks)
        // Rows 0/14: 4,5,4. Rows 3/11: 5,3,5. Rows 4/10: 6,6.
        // Rows 5/9: 4,5,4. Row 7: 3,7,3.
        $classic = $this->symmetricBlocks(15, 15, [
            [0, 4], [0, 10],
            [3, 5], [3, 9],
            [4, 0], [4, 7],
            [5, 4], [5, 10],
            [7, 3],
        ]);

        // Pattern 3 - "Staircase" (18 blocks)
        // Diagonal stepping pattern.
        $staircase = $this->symmetricBlocks(15, 15, [
            [0, 3], [0, 11],
            [3, 0], [3, 5], [3, 9],
            [5, 4], [5, 10],
            [7, 7],
        ]);

        // Pattern 4 - "Grid" (15 blocks)
        // Regular grid intersections.
        $grid = $this->symmetricBlocks(15, 15, [
            [0, 4], [0, 10],
            [3, 7],
            [4, 0],
            [5, 4], [5, 10],
            [7, 7],
        ]);

        // Pattern 5 - "Diamond" (14 blocks)
        // Diamond-shaped block arrangement.
        $diamond = $this->symmetricBlocks(15, 15, [
            [0, 7],
            [3, 4], [3, 10],
            [4, 3],
            [7, 0], [7, 5],
        ]);

        return [
            ['name' => 'Wide Open', 'grid' => $this->buildGrid(15, 15, $wideOpen)],
            ['name' => 'Classic', 'grid' => $this->buildGrid(15, 15, $classic)],
            ['name' => 'Staircase', 'grid' => $this->buildGrid(15, 15, $staircase)],
            ['name' => 'Grid', 'grid' => $this->buildGrid(15, 15, $grid)],
            ['name' => 'Diamond', 'grid' => $this->buildGrid(15, 15, $diamond)],
        ];
    }

    /**
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates21x21(): array
    {
        // Pattern 1 - "Sunday Open" (12 blocks)
        $open = $this->symmetricBlocks(21, 21, [
            [0, 6], [0, 14],
            [6, 0], [6, 10],
            [10, 6],
        ]);

        // Pattern 2 - "Sunday Classic" (20 blocks)
        $classic = $this->symmetricBlocks(21, 21, [
            [0, 4], [0, 10], [0, 16],
            [4, 0], [4, 8],
            [8, 4],
            [10, 7],
        ]);

        // Pattern 3 - "Sunday Spiral" (24 blocks)
        $spiral = $this->symmetricBlocks(21, 21, [
            [0, 5], [0, 15],
            [3, 0], [3, 8], [3, 12],
            [5, 5],
            [7, 3], [7, 10],
            [10, 0], [10, 7],
        ]);

        return [
            ['name' => 'Sunday Open', 'grid' => $this->buildGrid(21, 21, $open)],
            ['name' => 'Sunday Classic', 'grid' => $this->buildGrid(21, 21, $classic)],
            ['name' => 'Sunday Spiral', 'grid' => $this->buildGrid(21, 21, $spiral)],
        ];
    }
}
