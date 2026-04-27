<?php

namespace App\Services;

use App\Models\Crossword;
use App\Models\Template;
use Illuminate\Support\Facades\Cache;

class GridTemplateProvider
{
    /**
     * Get available grid templates for the given dimensions.
     *
     * @return array<int, array{name: string, grid: array<int, array<int, int|string>>, styles: array<string, array<string, mixed>>|null}>
     */
    public function getTemplates(int $width, int $height): array
    {
        // Only support square grids from 3x3 to 27x27
        if ($width !== $height || $width < 3 || $width > 27) {
            return [];
        }

        $fromAdmin = $this->templatesFromAdmin($width, $height);

        if (count($fromAdmin) >= 5) {
            return array_slice($fromAdmin, 0, 5);
        }

        $fromDb = $this->templatesFromDatabase($width, $height);
        $seen = array_flip(array_column($fromAdmin, 'name'));
        $merged = $fromAdmin;

        foreach ($fromDb as $template) {
            if (count($merged) >= 5) {
                break;
            }

            if (! isset($seen[$template['name']])) {
                $merged[] = $template;
                $seen[$template['name']] = true;
            }
        }

        if (count($merged) >= 5) {
            return $merged;
        }

        // Fill remaining slots with generated templates
        $generated = $this->generateTemplates($width, $height);

        foreach ($generated as $template) {
            if (count($merged) >= 5) {
                break;
            }

            if (! isset($seen[$template['name']])) {
                $merged[] = $template;
                $seen[$template['name']] = true;
            }
        }

        return $merged;
    }

    /**
     * Fetch admin-curated templates for the requested dimensions.
     *
     * @return array<int, array{name: string, grid: array<int, array<int, int|string>>, styles: array<string, array<string, mixed>>|null}>
     */
    private function templatesFromAdmin(int $width, int $height): array
    {
        return Template::query()
            ->where('is_active', true)
            ->where('width', $width)
            ->where('height', $height)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'grid', 'styles'])
            ->map(fn (Template $template): array => [
                'name' => $template->name,
                'grid' => $template->grid,
                'styles' => $template->styles,
            ])
            ->all();
    }

    /**
     * Extract unique grid layouts from published crosswords in the database.
     *
     * @return array<int, array{name: string, grid: array<int, array<int, int|string>>, styles: array<string, array<string, mixed>>|null}>
     */
    private function templatesFromDatabase(int $width, int $height): array
    {
        $cacheKey = "grid_templates_{$width}x{$height}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($width, $height) {
            $crosswords = Crossword::where('is_published', true)
                ->where('width', $width)
                ->where('height', $height)
                ->whereNotNull('grid')
                ->get(['id', 'title', 'grid']);

            if ($crosswords->isEmpty()) {
                return [];
            }

            $templates = [];
            $seen = [];

            foreach ($crosswords as $crossword) {
                $grid = $crossword->grid;

                if (! is_array($grid) || count($grid) !== $height) {
                    continue;
                }

                // Convert solution grid to template grid (# stays, letters become 0)
                $templateGrid = [];

                foreach ($grid as $row) {
                    if (! is_array($row) || count($row) !== $width) {
                        continue 2;
                    }

                    $templateRow = [];

                    foreach ($row as $cell) {
                        $templateRow[] = ($cell === '#' || $cell === null) ? '#' : 0;
                    }

                    $templateGrid[] = $templateRow;
                }

                // Deduplicate by block pattern fingerprint
                $fingerprint = $this->gridFingerprint($templateGrid);

                if (isset($seen[$fingerprint])) {
                    continue;
                }

                $seen[$fingerprint] = true;

                // Validate symmetry and minimum word length
                if (! self::hasRotationalSymmetry($templateGrid, $width, $height)) {
                    continue;
                }

                if (! self::validateMinWordLength($templateGrid, $width, $height)) {
                    continue;
                }

                $blockCount = $this->countBlocks($templateGrid);
                $name = $this->nameForLayout($templateGrid, $width, $height, $blockCount);

                $templates[] = [
                    'name' => $name,
                    'grid' => $templateGrid,
                    'block_count' => $blockCount,
                ];

                if (count($templates) >= 10) {
                    break;
                }
            }

            // Sort by block count to give variety (sparse to dense)
            usort($templates, fn ($a, $b) => $a['block_count'] <=> $b['block_count']);

            // Deduplicate names by appending numbers
            $nameCounts = [];
            $result = [];

            foreach ($templates as $t) {
                $baseName = $t['name'];

                if (isset($nameCounts[$baseName])) {
                    $nameCounts[$baseName]++;
                    $t['name'] = $baseName.' '.$nameCounts[$baseName];
                } else {
                    $nameCounts[$baseName] = 1;
                }

                $result[] = ['name' => $t['name'], 'grid' => $t['grid'], 'styles' => null];
            }

            return $result;
        });
    }

    /**
     * Generate a fingerprint for a grid layout based on block positions.
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    private function gridFingerprint(array $grid): string
    {
        $blocks = [];

        foreach ($grid as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === '#') {
                    $blocks[] = "{$r},{$c}";
                }
            }
        }

        return implode('|', $blocks);
    }

    /**
     * Count the number of block cells in a grid.
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    private function countBlocks(array $grid): int
    {
        $count = 0;

        foreach ($grid as $row) {
            foreach ($row as $cell) {
                if ($cell === '#') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Assign a descriptive name based on block density and pattern.
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    private function nameForLayout(array $grid, int $width, int $height, int $blockCount): string
    {
        $totalCells = $width * $height;
        $density = $blockCount / $totalCells;

        if ($blockCount === 0) {
            return 'Open';
        }

        if ($density < 0.08) {
            return 'Sparse';
        }

        if ($density < 0.14) {
            return 'Classic';
        }

        if ($density < 0.20) {
            return 'Standard';
        }

        return 'Dense';
    }

    /**
     * Check for 180-degree rotational symmetry.
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    public static function hasRotationalSymmetry(array $grid, int $width, int $height): bool
    {
        for ($r = 0; $r < $height; $r++) {
            for ($c = 0; $c < $width; $c++) {
                $mr = $height - 1 - $r;
                $mc = $width - 1 - $c;

                if (($grid[$r][$c] === '#') !== ($grid[$mr][$mc] === '#')) {
                    return false;
                }
            }
        }

        return true;
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
     * Validate that all words (consecutive non-block cells) are at least the minimum length.
     *
     * @param  array<int, array<int, int|string>>  $grid
     */
    public static function validateMinWordLength(array $grid, int $width, int $height, int $minLength = 3): bool
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
     * Generate templates parametrically for sizes without enough database examples.
     *
     * @return array<int, array{name: string, grid: array<int, array<int, int|string>>, styles: array<string, array<string, mixed>>|null}>
     */
    public function generateTemplates(int $width, int $height): array
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

            if (self::validateMinWordLength($grid, $n, $n)) {
                $templates[] = ['name' => $candidate['name'], 'grid' => $grid, 'styles' => null];
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
