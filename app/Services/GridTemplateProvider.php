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
            '2x2' => [],
            '5x5' => $this->templates5x5(),
            '11x11' => $this->templates11x11(),
            '13x13' => $this->templates13x13(),
            '15x15' => $this->templates15x15(),
            '17x17' => $this->templates17x17(),
            '21x21' => $this->templates21x21(),
            default => $this->generateTemplates($width, $height),
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
     * 5x5 grids are highly constrained (min 3-letter words + symmetry).
     * Only corner positions produce valid block placements.
     *
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates5x5(): array
    {
        return [
            ['name' => 'Open', 'grid' => $this->buildGrid(5, 5, [])],
            ['name' => 'Right Corner', 'grid' => $this->buildGrid(5, 5, $this->symmetricBlocks(5, 5, [[0, 4]]))],
            ['name' => 'Left Corner', 'grid' => $this->buildGrid(5, 5, $this->symmetricBlocks(5, 5, [[0, 0]]))],
            ['name' => 'Frame', 'grid' => $this->buildGrid(5, 5, $this->symmetricBlocks(5, 5, [[0, 0], [0, 4]]))],
        ];
    }

    /**
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates11x11(): array
    {
        // "Open" - minimal blocks (6 blocks)
        $open = $this->symmetricBlocks(11, 11, [
            [0, 5], [4, 0], [5, 3],
        ]);

        // "Cross" - center cross pattern (10 blocks)
        $cross = $this->symmetricBlocks(11, 11, [
            [0, 5], [3, 0], [3, 10], [5, 3], [5, 7],
        ]);

        // "Classic" - balanced themed layout (10 blocks)
        $classic = $this->symmetricBlocks(11, 11, [
            [0, 4], [0, 10], [3, 5], [4, 3], [5, 0],
        ]);

        // "Staircase" - diagonal stepping pattern (7 blocks)
        $staircase = $this->symmetricBlocks(11, 11, [
            [0, 3], [3, 0], [4, 7], [5, 5],
        ]);

        // "Diamond" - diamond-shaped block arrangement (9 blocks)
        $diamond = $this->symmetricBlocks(11, 11, [
            [0, 3], [0, 7], [3, 0], [3, 10], [5, 5],
        ]);

        return [
            ['name' => 'Open', 'grid' => $this->buildGrid(11, 11, $open)],
            ['name' => 'Cross', 'grid' => $this->buildGrid(11, 11, $cross)],
            ['name' => 'Classic', 'grid' => $this->buildGrid(11, 11, $classic)],
            ['name' => 'Staircase', 'grid' => $this->buildGrid(11, 11, $staircase)],
            ['name' => 'Diamond', 'grid' => $this->buildGrid(11, 11, $diamond)],
        ];
    }

    /**
     * Based on chaim.ipuz example pattern.
     *
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates13x13(): array
    {
        // From chaim.ipuz - 36 blocks, dense themed layout
        $dense = $this->symmetricBlocks(13, 13, [
            [0, 5], [0, 9], [1, 5], [1, 9], [2, 9],
            [3, 0], [3, 1], [3, 2], [3, 6], [3, 7], [3, 8],
            [4, 4], [5, 3], [5, 4], [5, 5], [5, 10], [5, 11], [5, 12],
        ]);

        // "Open" - minimal blocks (6 blocks)
        $open = $this->symmetricBlocks(13, 13, [
            [0, 6], [5, 0], [6, 4],
        ]);

        // "Classic" - balanced themed layout (10 blocks)
        $classic = $this->symmetricBlocks(13, 13, [
            [0, 4], [0, 9], [4, 0], [4, 6], [6, 4],
        ]);

        // "Lattice" - regular grid-like pattern (9 blocks)
        $lattice = $this->symmetricBlocks(13, 13, [
            [0, 4], [0, 8], [4, 0], [4, 12], [6, 6],
        ]);

        // "Diamond" - diamond-shaped arrangement (8 blocks)
        $diamond = $this->symmetricBlocks(13, 13, [
            [0, 6], [3, 3], [3, 9], [6, 0],
        ]);

        return [
            ['name' => 'Dense', 'grid' => $this->buildGrid(13, 13, $dense)],
            ['name' => 'Open', 'grid' => $this->buildGrid(13, 13, $open)],
            ['name' => 'Classic', 'grid' => $this->buildGrid(13, 13, $classic)],
            ['name' => 'Lattice', 'grid' => $this->buildGrid(13, 13, $lattice)],
            ['name' => 'Diamond', 'grid' => $this->buildGrid(13, 13, $diamond)],
        ];
    }

    /**
     * Based on real crossword puzzle layouts from examples.
     *
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates15x15(): array
    {
        // From Diehl_So_Shy.ipuz - 27 blocks, open layout
        $open = $this->symmetricBlocks(15, 15, [
            [0, 10], [1, 10], [2, 10],
            [3, 6],
            [4, 0], [4, 1], [4, 2], [4, 8],
            [5, 0], [5, 5], [5, 9],
            [6, 4], [6, 11],
            [7, 7],
        ]);

        // From twittercharges.ipuz - 36 blocks, standard themed
        $classic = $this->symmetricBlocks(15, 15, [
            [0, 3], [0, 8],
            [1, 3], [1, 8],
            [2, 3], [2, 8],
            [3, 6], [3, 10], [3, 14],
            [4, 5], [4, 13], [4, 14],
            [5, 0], [5, 1], [5, 7], [5, 11],
            [6, 8],
            [7, 4],
        ]);

        // From luckyguy.ipuz - 38 blocks, column stacks
        $columns = $this->symmetricBlocks(15, 15, [
            [0, 4], [0, 10],
            [1, 4], [1, 10],
            [2, 4], [2, 10],
            [3, 12], [3, 13], [3, 14],
            [4, 0], [4, 1], [4, 2], [4, 7], [4, 8],
            [5, 6], [5, 11],
            [6, 11],
            [7, 4], [7, 5],
        ]);

        // From purplereign.ipuz - 38 blocks, pinwheel style
        $pinwheel = $this->symmetricBlocks(15, 15, [
            [0, 5], [0, 6], [0, 11],
            [1, 5], [1, 11],
            [2, 11],
            [3, 4], [3, 10],
            [4, 0], [4, 1], [4, 6], [4, 7],
            [5, 12], [5, 13], [5, 14],
            [6, 3], [6, 8], [6, 9],
            [7, 4],
        ]);

        // From piece.ipuz - 41 blocks, dense pattern
        $dense = $this->symmetricBlocks(15, 15, [
            [0, 3], [0, 7], [0, 11],
            [1, 3], [1, 7], [1, 11],
            [2, 3], [2, 11],
            [3, 13], [3, 14],
            [4, 5], [4, 6], [4, 10], [4, 14],
            [5, 4],
            [6, 0], [6, 1], [6, 2], [6, 8], [6, 9],
            [7, 7],
        ]);

        return [
            ['name' => 'Open', 'grid' => $this->buildGrid(15, 15, $open)],
            ['name' => 'Classic', 'grid' => $this->buildGrid(15, 15, $classic)],
            ['name' => 'Columns', 'grid' => $this->buildGrid(15, 15, $columns)],
            ['name' => 'Pinwheel', 'grid' => $this->buildGrid(15, 15, $pinwheel)],
            ['name' => 'Dense', 'grid' => $this->buildGrid(15, 15, $dense)],
        ];
    }

    /**
     * Based on real 17x17 crossword puzzle layouts from examples.
     *
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates17x17(): array
    {
        // From Zodiac.ipuz - 44 blocks
        $zodiac = $this->symmetricBlocks(17, 17, [
            [0, 5], [0, 12], [1, 5], [1, 12],
            [3, 3], [3, 7], [3, 8], [3, 13],
            [4, 0], [4, 1], [4, 2], [4, 7], [4, 11],
            [6, 5], [6, 6], [6, 10], [6, 14], [6, 15], [6, 16],
            [7, 4], [7, 9], [7, 13],
        ]);

        // From fab15.ipuz - 53 blocks, dense layout
        $dense = $this->symmetricBlocks(17, 17, [
            [0, 3], [0, 7], [0, 12], [0, 13],
            [1, 3], [1, 7], [1, 13],
            [2, 13],
            [3, 0], [3, 8], [3, 13],
            [4, 0], [4, 1], [4, 2], [4, 8],
            [5, 6], [5, 11], [5, 16],
            [6, 5], [6, 10], [6, 14], [6, 15], [6, 16],
            [7, 4], [7, 9],
            [8, 3], [8, 8],
        ]);

        // From nifty90.ipuz - 47 blocks
        $classic = $this->symmetricBlocks(17, 17, [
            [0, 3], [0, 7], [0, 12],
            [1, 3], [1, 12],
            [3, 8], [3, 9], [3, 13],
            [4, 7],
            [5, 0], [5, 1], [5, 5], [5, 6], [5, 10], [5, 14], [5, 15], [5, 16],
            [6, 0], [6, 4], [6, 12],
            [7, 11],
            [8, 3], [8, 7], [8, 8],
        ]);

        // "Open" - sparse layout (6 blocks)
        $open = $this->symmetricBlocks(17, 17, [
            [0, 8], [5, 0], [8, 5],
        ]);

        // "Staircase" - diagonal stepping (10 blocks)
        $staircase = $this->symmetricBlocks(17, 17, [
            [0, 4], [3, 0], [4, 8], [6, 4], [8, 8],
        ]);

        return [
            ['name' => 'Zodiac', 'grid' => $this->buildGrid(17, 17, $zodiac)],
            ['name' => 'Dense', 'grid' => $this->buildGrid(17, 17, $dense)],
            ['name' => 'Classic', 'grid' => $this->buildGrid(17, 17, $classic)],
            ['name' => 'Open', 'grid' => $this->buildGrid(17, 17, $open)],
            ['name' => 'Staircase', 'grid' => $this->buildGrid(17, 17, $staircase)],
        ];
    }

    /**
     * Based on real Sunday crossword puzzle layouts from examples.
     *
     * @return array<int, array{name: string, grid: array}>
     */
    private function templates21x21(): array
    {
        // From Duluth.ipuz - 80 blocks, dense Sunday layout
        $dense = $this->symmetricBlocks(21, 21, [
            [0, 6], [0, 7], [0, 11], [0, 17],
            [1, 6], [1, 11], [1, 17],
            [2, 17],
            [3, 4], [3, 5], [3, 10], [3, 16],
            [4, 12],
            [5, 0], [5, 1], [5, 2], [5, 7], [5, 13], [5, 14], [5, 18], [5, 19], [5, 20],
            [6, 0], [6, 8], [6, 15],
            [7, 9],
            [8, 3], [8, 4], [8, 5], [8, 10], [8, 11], [8, 12], [8, 16], [8, 17],
            [9, 6], [9, 7], [9, 12], [9, 18], [9, 19], [9, 20],
        ]);

        // From rockclock.ipuz - 64 blocks, classic Sunday layout
        $classic = $this->symmetricBlocks(21, 21, [
            [0, 4], [0, 10], [0, 17],
            [1, 4], [1, 10], [1, 17],
            [2, 17],
            [3, 5], [3, 12],
            [4, 0], [4, 7], [4, 11], [4, 16],
            [5, 6],
            [6, 3], [6, 4], [6, 9], [6, 13], [6, 18], [6, 19], [6, 20],
            [7, 10], [7, 14],
            [8, 0], [8, 1], [8, 2], [8, 8], [8, 15],
            [9, 7], [9, 11], [9, 16],
            [10, 3],
        ]);

        // "Open" - wide-open Sunday grid (8 blocks)
        $open = $this->symmetricBlocks(21, 21, [
            [0, 7], [0, 13], [7, 0], [10, 7],
        ]);

        // "Staircase" - diagonal stepping pattern (12 blocks)
        $staircase = $this->symmetricBlocks(21, 21, [
            [0, 4], [3, 0], [4, 8], [6, 4], [8, 10], [10, 7],
        ]);

        // "Lattice" - regular grid pattern (20 blocks)
        $lattice = $this->symmetricBlocks(21, 21, [
            [0, 5], [0, 10], [0, 15], [4, 0], [4, 7],
            [4, 13], [7, 4], [7, 10], [10, 0], [10, 7],
        ]);

        return [
            ['name' => 'Dense', 'grid' => $this->buildGrid(21, 21, $dense)],
            ['name' => 'Classic', 'grid' => $this->buildGrid(21, 21, $classic)],
            ['name' => 'Open', 'grid' => $this->buildGrid(21, 21, $open)],
            ['name' => 'Staircase', 'grid' => $this->buildGrid(21, 21, $staircase)],
            ['name' => 'Lattice', 'grid' => $this->buildGrid(21, 21, $lattice)],
        ];
    }

    /**
     * Validate that all words (consecutive non-block cells) are at least the minimum length.
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    private function validateMinWordLength(array $grid, int $width, int $height, int $minLength = 3): bool
    {
        // Check across words
        for ($r = 0; $r < $height; $r++) {
            $len = 0;

            for ($c = 0; $c <= $width; $c++) {
                if ($c < $width && $grid[$r][$c] !== '#') {
                    $len++;
                } else {
                    if ($len > 0 && $len < $minLength) {
                        return false;
                    }
                    $len = 0;
                }
            }
        }

        // Check down words
        for ($c = 0; $c < $width; $c++) {
            $len = 0;

            for ($r = 0; $r <= $height; $r++) {
                if ($r < $height && $grid[$r][$c] !== '#') {
                    $len++;
                } else {
                    if ($len > 0 && $len < $minLength) {
                        return false;
                    }
                    $len = 0;
                }
            }
        }

        return true;
    }

    /**
     * Generate templates parametrically for sizes without hand-crafted examples.
     *
     * @return array<int, array{name: string, grid: array<int, array<int, int|string>>}>
     */
    private function generateTemplates(int $width, int $height): array
    {
        // Only support square grids from 3x3 to 27x27
        if ($width !== $height || $width < 3 || $width > 27) {
            return [];
        }

        $n = $width;

        $candidates = [];

        // "Open" - no blocks, always valid for n >= 3
        $candidates[] = ['name' => 'Open', 'blocks' => []];

        // Generate size-appropriate candidates
        if ($n <= 6) {
            // Small grids: only corner blocks are safe
            $candidates[] = ['name' => 'Corner', 'blocks' => [[0, 0]]];
            $candidates[] = ['name' => 'Right Corner', 'blocks' => [[0, $n - 1]]];

            if ($n >= 5) {
                $candidates[] = ['name' => 'Frame', 'blocks' => [[0, 0], [0, $n - 1]]];
            }
        } elseif ($n <= 10) {
            // Medium grids: corners + simple interior
            $candidates[] = ['name' => 'Corner', 'blocks' => [[0, 0]]];
            $candidates[] = ['name' => 'Right Corner', 'blocks' => [[0, $n - 1]]];
            $candidates[] = ['name' => 'Frame', 'blocks' => [[0, 0], [0, $n - 1]]];

            if ($n % 2 === 1) {
                $mid = intdiv($n, 2);
                $candidates[] = ['name' => 'Diamond', 'blocks' => [[0, $mid], [$mid, 0]]];
                $candidates[] = ['name' => 'Cross', 'blocks' => [[0, $mid], [0, 0], [$mid, 0]]];
                $candidates[] = ['name' => 'Classic', 'blocks' => [[0, 0], [0, $mid]]];
            } else {
                $candidates[] = ['name' => 'Diamond', 'blocks' => [[0, 3], [3, 0]]];
                $candidates[] = ['name' => 'Cross', 'blocks' => [[0, 3], [0, $n - 4], [3, 0]]];
                $candidates[] = ['name' => 'Classic', 'blocks' => [[0, 0], [0, $n - 1], [3, 0]]];
            }
        } else {
            // Large grids (n >= 11): rich interior patterns
            $this->addLargeGridCandidates($candidates, $n);
        }

        // Build and validate each candidate
        $templates = [];

        foreach ($candidates as $candidate) {
            $blocks = $this->symmetricBlocks($n, $n, $candidate['blocks']);
            $grid = $this->buildGrid($n, $n, $blocks);

            if ($this->validateMinWordLength($grid, $n, $n)) {
                $templates[] = ['name' => $candidate['name'], 'grid' => $grid];
            }

            if (count($templates) >= 5) {
                break;
            }
        }

        return $templates;
    }

    /**
     * Add candidate block patterns for large grids (n >= 11).
     *
     * @param  array<int, array{name: string, blocks: array<int, array{int, int}>}>  $candidates
     */
    private function addLargeGridCandidates(array &$candidates, int $n): void
    {
        $mid = intdiv($n, 2);
        $isOdd = $n % 2 === 1;

        // "Corner" - blocks in corners only
        $candidates[] = ['name' => 'Corner', 'blocks' => [[0, 0], [0, $n - 1]]];

        // "Cross" - center cross pattern
        if ($isOdd) {
            $candidates[] = ['name' => 'Cross', 'blocks' => [
                [0, $mid], [3, 0], [3, $n - 1], [$mid, 3], [$mid, $n - 4],
            ]];
        } else {
            $candidates[] = ['name' => 'Cross', 'blocks' => [
                [0, 3], [0, $n - 4], [3, 0], [3, $n - 1],
            ]];
        }

        // "Staircase" - diagonal stepping
        $stairBlocks = [];
        $step = max(3, intdiv($n, 4));

        for ($i = 0; $i < 4; $i++) {
            $r = $i * $step;
            $c = ($i % 2 === 0) ? intdiv($n, 5) : $n - 1 - intdiv($n, 5);

            if ($r < $mid || ($isOdd && $r === $mid)) {
                $stairBlocks[] = [$r, $c];
            }
        }
        $candidates[] = ['name' => 'Staircase', 'blocks' => $stairBlocks];

        // "Diamond" - diamond-shaped arrangement
        if ($isOdd) {
            $candidates[] = ['name' => 'Diamond', 'blocks' => [
                [0, $mid], [$mid, 0], [intdiv($mid, 2), intdiv($mid, 2)], [intdiv($mid, 2), $n - 1 - intdiv($mid, 2)],
            ]];
        } else {
            $q = intdiv($n, 4);
            $candidates[] = ['name' => 'Diamond', 'blocks' => [
                [0, $q], [0, $n - 1 - $q], [$q, 0], [$q, $n - 1],
            ]];
        }

        // "Classic" - balanced themed layout with more blocks
        $classicBlocks = [[0, intdiv($n, 3)]];

        if ($isOdd) {
            $classicBlocks[] = [0, $n - 1 - intdiv($n, 3)];
            $classicBlocks[] = [intdiv($n, 3), 0];
            $classicBlocks[] = [intdiv($n, 3), $mid];
            $classicBlocks[] = [$mid, intdiv($n, 3)];
        } else {
            $classicBlocks[] = [0, $n - 1 - intdiv($n, 3)];
            $classicBlocks[] = [intdiv($n, 3), 0];
            $classicBlocks[] = [intdiv($n, 3), $n - 1];
        }
        $candidates[] = ['name' => 'Classic', 'blocks' => $classicBlocks];

        // "Dense" - more blocks for larger grids
        if ($n >= 14) {
            $denseBlocks = [];
            $third = intdiv($n, 3);
            $twoThird = $n - 1 - $third;

            $denseBlocks[] = [0, $third];
            $denseBlocks[] = [0, $twoThird];
            $denseBlocks[] = [$third, 0];
            $denseBlocks[] = [$third, $n - 1];

            if ($isOdd) {
                $denseBlocks[] = [$mid, $third];
            }

            // Add edge blocks for more density
            $denseBlocks[] = [1, $third];
            $denseBlocks[] = [1, $twoThird];

            $candidates[] = ['name' => 'Dense', 'blocks' => $denseBlocks];
        }

        // "Lattice" - regular grid-like pattern
        $latticeBlocks = [];
        $spacing = max(4, intdiv($n, 3));

        for ($r = 0; $r <= $mid; $r += $spacing) {
            for ($c = $spacing; $c < $n - $spacing; $c += $spacing) {
                if ($r < $mid || ($isOdd && $r === $mid)) {
                    $latticeBlocks[] = [$r, $c];
                }
            }
        }
        $candidates[] = ['name' => 'Lattice', 'blocks' => $latticeBlocks];
    }
}
