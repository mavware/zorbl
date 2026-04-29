<?php

namespace App\Services;

use App\Models\Template;
use App\Support\TemplateStats;
use Zorbl\CrosswordIO\GridNumberer;

/**
 * Computes structural stats for a crossword template grid.
 *
 * Symmetry checks compare block positions only and ignore bar positions
 * in the styles JSON; this is intentional for v1 since the only bar-using
 * template in the curated set has rotationally-placed bars on an all-white
 * grid.
 */
class TemplateStatsService
{
    public function __construct(private GridNumberer $numberer) {}

    public function forTemplate(Template $template): TemplateStats
    {
        return $this->forGrid(
            $template->grid,
            $template->width,
            $template->height,
            $template->styles ?? [],
        );
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    public function forGrid(array $grid, int $width, int $height, array $styles = []): TemplateStats
    {
        $cellCount = $width * $height;
        $blockCount = $this->countBlocks($grid, $width, $height);
        $whiteCount = $cellCount - $blockCount;

        $numbered = $this->numberer->number($grid, $width, $height, $styles, 2);
        $acrossWords = $numbered['across'];
        $downWords = $numbered['down'];

        $lengths = [];
        foreach ($acrossWords as $word) {
            $lengths[] = $word['length'];
        }
        foreach ($downWords as $word) {
            $lengths[] = $word['length'];
        }

        $wordCount = count($lengths);
        $minLen = $wordCount > 0 ? min($lengths) : 0;
        $maxLen = $wordCount > 0 ? max($lengths) : 0;
        $avgLen = $wordCount > 0 ? array_sum($lengths) / $wordCount : 0.0;

        return new TemplateStats(
            width: $width,
            height: $height,
            cellCount: $cellCount,
            blockCount: $blockCount,
            blockDensity: $cellCount > 0 ? $blockCount / $cellCount : 0.0,
            whiteCount: $whiteCount,
            acrossWordCount: count($acrossWords),
            downWordCount: count($downWords),
            wordCount: $wordCount,
            minWordLength: $minLen,
            maxWordLength: $maxLen,
            avgWordLength: $avgLen,
            isRotationallySymmetric: GridTemplateProvider::hasRotationalSymmetry($grid, $width, $height),
            isMirrorHorizontal: $this->hasMirrorHorizontalSymmetry($grid, $width, $height),
            isMirrorVertical: $this->hasMirrorVerticalSymmetry($grid, $width, $height),
            isFullyChecked: $this->isFullyChecked($grid, $width, $height, $acrossWords, $downWords),
            isConnected: $this->isConnected($grid, $width, $height, $styles),
        );
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     */
    private function countBlocks(array $grid, int $width, int $height): int
    {
        $count = 0;
        for ($r = 0; $r < $height; $r++) {
            for ($c = 0; $c < $width; $c++) {
                if ($this->isBlock($grid, $r, $c)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     */
    private function hasMirrorHorizontalSymmetry(array $grid, int $width, int $height): bool
    {
        for ($r = 0; $r < $height; $r++) {
            for ($c = 0; $c < intdiv($width, 2); $c++) {
                if ($this->isBlock($grid, $r, $c) !== $this->isBlock($grid, $r, $width - 1 - $c)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     */
    private function hasMirrorVerticalSymmetry(array $grid, int $width, int $height): bool
    {
        for ($r = 0; $r < intdiv($height, 2); $r++) {
            for ($c = 0; $c < $width; $c++) {
                if ($this->isBlock($grid, $r, $c) !== $this->isBlock($grid, $height - 1 - $r, $c)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     * @param  list<array{number: int, row: int, col: int, length: int}>  $acrossWords
     * @param  list<array{number: int, row: int, col: int, length: int}>  $downWords
     */
    private function isFullyChecked(array $grid, int $width, int $height, array $acrossWords, array $downWords): bool
    {
        $inAcross = array_fill(0, $height, array_fill(0, $width, false));
        $inDown = array_fill(0, $height, array_fill(0, $width, false));

        foreach ($acrossWords as $word) {
            for ($i = 0; $i < $word['length']; $i++) {
                $inAcross[$word['row']][$word['col'] + $i] = true;
            }
        }

        foreach ($downWords as $word) {
            for ($i = 0; $i < $word['length']; $i++) {
                $inDown[$word['row'] + $i][$word['col']] = true;
            }
        }

        for ($r = 0; $r < $height; $r++) {
            for ($c = 0; $c < $width; $c++) {
                if ($this->isBlock($grid, $r, $c)) {
                    continue;
                }
                if (! $inAcross[$r][$c] || ! $inDown[$r][$c]) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<int, int|string|null>>  $grid
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function isConnected(array $grid, int $width, int $height, array $styles): bool
    {
        $startR = -1;
        $startC = -1;
        $whiteCount = 0;

        for ($r = 0; $r < $height; $r++) {
            for ($c = 0; $c < $width; $c++) {
                if (! $this->isBlock($grid, $r, $c)) {
                    if ($startR === -1) {
                        $startR = $r;
                        $startC = $c;
                    }
                    $whiteCount++;
                }
            }
        }

        if ($whiteCount === 0) {
            return true;
        }

        $visited = array_fill(0, $height, array_fill(0, $width, false));
        $visited[$startR][$startC] = true;
        $queue = [[$startR, $startC]];
        $reached = 1;

        while (! empty($queue)) {
            [$r, $c] = array_shift($queue);

            $neighbors = [
                [$r - 1, $c],
                [$r + 1, $c],
                [$r, $c - 1],
                [$r, $c + 1],
            ];

            foreach ($neighbors as [$nr, $nc]) {
                if ($nr < 0 || $nr >= $height || $nc < 0 || $nc >= $width) {
                    continue;
                }
                if ($visited[$nr][$nc]) {
                    continue;
                }
                if ($this->isBlock($grid, $nr, $nc)) {
                    continue;
                }
                if ($this->hasBarBetween($r, $c, $nr, $nc, $styles)) {
                    continue;
                }
                $visited[$nr][$nc] = true;
                $queue[] = [$nr, $nc];
                $reached++;
            }
        }

        return $reached === $whiteCount;
    }

    /**
     * @param  array<string, array{bars?: list<string>}>  $styles
     */
    private function hasBarBetween(int $r1, int $c1, int $r2, int $c2, array $styles): bool
    {
        if ($r1 === $r2 && $c2 === $c1 + 1) {
            return $this->hasBar($styles, $r1, $c1, 'right')
                || $this->hasBar($styles, $r2, $c2, 'left');
        }
        if ($r1 === $r2 && $c2 === $c1 - 1) {
            return $this->hasBar($styles, $r1, $c1, 'left')
                || $this->hasBar($styles, $r2, $c2, 'right');
        }
        if ($c1 === $c2 && $r2 === $r1 + 1) {
            return $this->hasBar($styles, $r1, $c1, 'bottom')
                || $this->hasBar($styles, $r2, $c2, 'top');
        }
        if ($c1 === $c2 && $r2 === $r1 - 1) {
            return $this->hasBar($styles, $r1, $c1, 'top')
                || $this->hasBar($styles, $r2, $c2, 'bottom');
        }

        return false;
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
     * @param  array<int, array<int, int|string|null>>  $grid
     */
    private function isBlock(array $grid, int $row, int $col): bool
    {
        $cell = $grid[$row][$col] ?? null;

        return $cell === '#' || $cell === null;
    }
}
