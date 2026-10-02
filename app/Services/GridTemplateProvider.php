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
        // Only support square grids from 3x3 to 35x35
        if ($width !== $height || $width < 3 || $width > 35) {
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
}
