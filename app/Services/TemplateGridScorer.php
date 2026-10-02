<?php

namespace App\Services;

use App\Support\TemplateScore;
use App\Support\TemplateTargets;

/**
 * Scores a square block pattern against construction rules and size-scaled
 * targets. Lower energy is better; any hard violation makes a grid unusable.
 *
 * Grids are scored in a padded flat form: a (size + 2) square where 0 is a
 * white cell, 1 a block and 2 the surrounding border, so neighbour lookups
 * never need bounds checks.
 */
class TemplateGridScorer
{
    public const int WHITE = 0;

    public const int BLOCK = 1;

    public const int BORDER = 2;

    private const float UNCHECKED_WEIGHT = 1000.0;

    private const float DISCONNECTED_WEIGHT = 1000.0;

    private const float BLACK_SQUARE_WEIGHT = 300.0;

    private const float BLACK_RUN_WEIGHT = 200.0;

    private const float TOUCHING_WEIGHT = 300.0;

    /**
     * @param  array<int, array<int, int|string|null>>  $grid  Rows of 0 (white) or '#' (block)
     */
    public function score(array $grid, TemplateTargets $targets): TemplateScore
    {
        $size = $targets->size;
        $measured = $this->measure(self::toCells($grid, $size), $targets);

        $violations = [];

        if ($measured['unchecked'] > 0) {
            $violations[] = sprintf('%d unchecked square(s) in only one entry', $measured['unchecked']);
        }
        if ($measured['components'] > 1) {
            $violations[] = sprintf('white squares are split into %d separate areas', $measured['components']);
        }
        if ($measured['blackSquares'] > 0) {
            $violations[] = sprintf('%d 2×2 clump(s) of blocks', $measured['blackSquares']);
        }
        if ($measured['blackRunOver'] > 0) {
            $violations[] = sprintf('a run of more than %d blocks in a row', $targets->maxBlackRun);
        }
        if ($measured['lonelyBlocks'] > 0) {
            $violations[] = sprintf('%d block(s) touching fewer than %d other block(s)', $measured['lonelyBlocks'], $targets->minTouchingBlocks);
        }

        $words = $measured['words'];
        $blocks = $measured['blocks'];

        return new TemplateScore(
            energy: $measured['energy'],
            hardViolations: $violations,
            blockCount: $blocks,
            blockDensity: $blocks / ($size * $size),
            wordCount: $words,
            averageLength: $words > 0 ? $measured['letters'] / $words : 0.0,
            lengthCounts: $measured['lengthCounts'],
            threeLetterShare: $words > 0 ? ($measured['lengthCounts'][3] ?? 0) / $words : 0.0,
            lengthDistance: $measured['lengthDistance'],
            largestOpenSquare: $measured['largestOpenSquare'],
            openWindowCount: $measured['openWindows'],
            cheaterCount: $measured['cheaters'],
            chokepointCount: $measured['chokepoints'],
            largestBlackShape: $measured['largestBlackShape'],
            fullWidthEntryCount: $measured['fullWidth'],
            borderShare: $blocks > 0 ? $measured['borderBlocks'] / $blocks : 0.0,
            lonelyBlockCount: $measured['lonelyBlocks'],
            shortEntryCount: $measured['shortEntries'],
            longEntryCount: $measured['longEntries'],
        );
    }

    /**
     * Measure a padded flat grid. This is the annealer's inner loop, so it
     * trades readability for a single pass per concern.
     *
     * @param  array<int, int>  $cells
     * @return array{energy: float, hard: float, unchecked: int, shortEntries: int, longEntries: int, components: int, blackSquares: int, blackRunOver: int, blocks: int, words: int, letters: int, lengthCounts: array<int, int>, lengthDistance: float, largestOpenSquare: int, openWindows: int, cheaters: int, chokepoints: int, largestBlackShape: int, fullWidth: int, borderBlocks: int, lonelyBlocks: int}
     */
    public function measure(array $cells, TemplateTargets $targets): array
    {
        $size = $targets->size;
        $stride = $size + 2;
        $maxRun = $targets->maxBlackRun;
        $minLength = $targets->minEntryLength;
        $maxLength = $targets->maxEntryLength;

        $lengthCounts = [];
        $unchecked = 0;
        $shortDeficit = 0;
        $shortEntries = 0;
        $longEntries = 0;
        $blackRunOver = 0;
        $longBlackRuns = 0;
        $longOver = 0;
        $words = 0;
        $letters = 0;
        $fullWidth = 0;

        // Entries and black runs, across (step 1) then down (step $stride).
        foreach ([[1, $stride], [$stride, 1]] as [$step, $lineStep]) {
            for ($line = 0; $line < $size; $line++) {
                $index = $stride + 1 + $line * $lineStep;
                $length = 0;
                $run = 0;

                for ($k = 0; $k <= $size; $k++, $index += $step) {
                    $cell = $cells[$index];

                    if ($cell === self::WHITE) {
                        $length++;
                        if ($run >= $maxRun) {
                            $longBlackRuns++;
                            $blackRunOver += $run - $maxRun;
                        }
                        $run = 0;

                        continue;
                    }

                    if ($length === 1) {
                        // A lone white cell is a letter checked in one direction only.
                        $unchecked++;
                        $length = 0;
                    } elseif ($length > 0) {
                        $lengthCounts[$length] = ($lengthCounts[$length] ?? 0) + 1;
                        $words++;
                        $letters += $length;
                        if ($length < $minLength) {
                            $shortEntries++;
                            $shortDeficit += $minLength - $length;
                        } elseif ($length > $maxLength) {
                            $longEntries++;
                            $longOver += $length - $maxLength;
                        }
                        if ($length === $size) {
                            $fullWidth++;
                        }
                        $length = 0;
                    }

                    if ($cell === self::BLOCK) {
                        $run++;
                    }
                }

                if ($run >= $maxRun) {
                    $longBlackRuns++;
                    $blackRunOver += $run - $maxRun;
                }
            }
        }

        [$components, $chokepoints] = $this->connectivity($cells, $size);

        $blocks = 0;
        $borderBlocks = 0;
        $blackSquares = 0;
        $cheaters = 0;
        $blackShapeOver = 0;
        $largestBlackShape = 0;
        $openWindows = 0;
        $largestOpenSquare = 0;
        $windowSize = $targets->openWindowSize;
        $maxShape = $targets->maxBlackShape;
        $minTouching = $targets->minTouchingBlocks;
        $touchingDeficit = 0;
        $lonelyBlocks = 0;
        $openSquare = array_fill(0, $stride * $stride, 0);
        $seenBlocks = [];

        for ($row = 1; $row <= $size; $row++) {
            $index = $row * $stride + 1;

            for ($col = 1; $col <= $size; $col++, $index++) {
                if ($cells[$index] === self::WHITE) {
                    $side = 1 + min($openSquare[$index - 1], $openSquare[$index - $stride], $openSquare[$index - $stride - 1]);
                    $openSquare[$index] = $side;
                    if ($side >= $windowSize) {
                        $openWindows++;
                    }
                    if ($side > $largestOpenSquare) {
                        $largestOpenSquare = $side;
                    }

                    continue;
                }

                $blocks++;

                if ($row === 1 || $row === $size || $col === 1 || $col === $size) {
                    $borderBlocks++;
                }

                if ($cells[$index + 1] === self::BLOCK && $cells[$index + $stride] === self::BLOCK && $cells[$index + $stride + 1] === self::BLOCK) {
                    $blackSquares++;
                }

                // A cheater separates no entries: in each direction at least
                // one neighbour is already a block or the border.
                $splitsAcross = $cells[$index - 1] === self::WHITE && $cells[$index + 1] === self::WHITE;
                $splitsDown = $cells[$index - $stride] === self::WHITE && $cells[$index + $stride] === self::WHITE;
                if (! $splitsAcross && ! $splitsDown) {
                    $cheaters++;
                }

                if ($minTouching > 0) {
                    // Other blocks among the eight surrounding cells; the border does not count.
                    $touching = 0;
                    foreach ([-$stride - 1, -$stride, -$stride + 1, -1, 1, $stride - 1, $stride, $stride + 1] as $offset) {
                        if ($cells[$index + $offset] === self::BLOCK) {
                            $touching++;
                        }
                    }

                    if ($touching < $minTouching) {
                        $lonelyBlocks++;
                        $touchingDeficit += $minTouching - $touching;
                    }
                }

                if (isset($seenBlocks[$index])) {
                    continue;
                }

                $shape = 0;
                $stack = [$index];
                $seenBlocks[$index] = true;

                while ($stack !== []) {
                    $current = array_pop($stack);
                    $shape++;

                    foreach ([$current - 1, $current + 1, $current - $stride, $current + $stride] as $next) {
                        if ($cells[$next] === self::BLOCK && ! isset($seenBlocks[$next])) {
                            $seenBlocks[$next] = true;
                            $stack[] = $next;
                        }
                    }
                }

                if ($shape > $largestBlackShape) {
                    $largestBlackShape = $shape;
                }
                if ($shape > $maxShape) {
                    $blackShapeOver += $shape - $maxShape;
                }
            }
        }

        $lengthDistance = 2.0;

        if ($words > 0) {
            $buckets = [3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0];
            foreach ($lengthCounts as $length => $count) {
                $buckets[min(max($length, 3), 8)] += $count;
            }

            $lengthDistance = 0.0;
            foreach ($buckets as $length => $count) {
                $lengthDistance += abs($count / $words - $targets->lengthShares[$length]);
            }
        }

        $hard = self::UNCHECKED_WEIGHT * $unchecked
            + self::DISCONNECTED_WEIGHT * max(0, $components - 1)
            + self::BLACK_SQUARE_WEIGHT * $blackSquares
            + self::BLACK_RUN_WEIGHT * $blackRunOver
            + self::TOUCHING_WEIGHT * $touchingDeficit;

        $weights = $targets->weights;

        $soft = $weights->shortEntry * $shortDeficit
            + $weights->longEntry * $longOver
            + $weights->blockBand * max(0, $targets->minBlocks - $blocks, $blocks - $targets->maxBlocks)
            + $weights->wordBand * max(0, $targets->minWords - $words, $words - $targets->maxWords)
            + $weights->lengthMix * $lengthDistance
            + $weights->openArea * $openWindows
            + $weights->cheater * max(0, $cheaters - $targets->maxCheaters)
            + $weights->blackShape * $blackShapeOver
            + $weights->chokepoint * $chokepoints
            + $weights->fullWidth * max(0, $fullWidth - $targets->maxFullWidthEntries)
            + $weights->border * max(0.0, $borderBlocks - $targets->maxBorderShare * $blocks)
            // A run at the limit is legal but rarer in published grids than a shorter one.
            + $weights->longBlackRun * $longBlackRuns;

        ksort($lengthCounts);

        return [
            'energy' => $hard + $soft,
            'hard' => $hard,
            'unchecked' => $unchecked,
            'shortEntries' => $shortEntries,
            'longEntries' => $longEntries,
            'components' => $components,
            'blackSquares' => $blackSquares,
            'blackRunOver' => $blackRunOver,
            'blocks' => $blocks,
            'words' => $words,
            'letters' => $letters,
            'lengthCounts' => $lengthCounts,
            'lengthDistance' => $lengthDistance,
            'largestOpenSquare' => $largestOpenSquare,
            'openWindows' => $openWindows,
            'cheaters' => $cheaters,
            'chokepoints' => $chokepoints,
            'largestBlackShape' => $largestBlackShape,
            'fullWidth' => $fullWidth,
            'borderBlocks' => $borderBlocks,
            'lonelyBlocks' => $lonelyBlocks,
        ];
    }

    /**
     * Convert a template grid into the padded flat form.
     *
     * @param  array<int, array<int, int|string|null>>  $grid
     * @return array<int, int>
     */
    public static function toCells(array $grid, int $size): array
    {
        $stride = $size + 2;
        $cells = array_fill(0, $stride * $stride, self::BORDER);

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                $cell = $grid[$row][$col] ?? null;
                $cells[($row + 1) * $stride + $col + 1] = ($cell === '#' || $cell === null) ? self::BLOCK : self::WHITE;
            }
        }

        return $cells;
    }

    /**
     * Convert the padded flat form back into a template grid.
     *
     * @param  array<int, int>  $cells
     * @return array<int, array<int, int|string>>
     */
    public static function toGrid(array $cells, int $size): array
    {
        $stride = $size + 2;
        $grid = [];

        for ($row = 0; $row < $size; $row++) {
            $gridRow = [];
            for ($col = 0; $col < $size; $col++) {
                $gridRow[] = $cells[($row + 1) * $stride + $col + 1] === self::BLOCK ? '#' : 0;
            }
            $grid[] = $gridRow;
        }

        return $grid;
    }

    /**
     * Count connected white areas and chokepoints (white cells whose removal
     * would split the grid) with one iterative depth-first pass.
     *
     * @param  array<int, int>  $cells
     * @return array{int, int}
     */
    private function connectivity(array $cells, int $size): array
    {
        $stride = $size + 2;
        $offsets = [-1, 1, -$stride, $stride];
        $discovered = [];
        $lowest = [];
        $parent = [];
        $nextOffset = [];
        $isChokepoint = [];
        $components = 0;
        $timer = 0;

        for ($row = 1; $row <= $size; $row++) {
            $root = $row * $stride + 1;

            for ($col = 1; $col <= $size; $col++, $root++) {
                if ($cells[$root] !== self::WHITE || isset($discovered[$root])) {
                    continue;
                }

                $components++;
                $rootChildren = 0;
                $discovered[$root] = $lowest[$root] = ++$timer;
                $parent[$root] = -1;
                $nextOffset[$root] = 0;
                $stack = [$root];
                $depth = 0;

                while ($depth >= 0) {
                    $current = $stack[$depth];

                    if ($nextOffset[$current] < 4) {
                        $next = $current + $offsets[$nextOffset[$current]++];

                        if ($cells[$next] !== self::WHITE) {
                            continue;
                        }

                        if (! isset($discovered[$next])) {
                            $discovered[$next] = $lowest[$next] = ++$timer;
                            $parent[$next] = $current;
                            $nextOffset[$next] = 0;
                            $stack[++$depth] = $next;
                            if ($current === $root) {
                                $rootChildren++;
                            }
                        } elseif ($next !== $parent[$current] && $discovered[$next] < $lowest[$current]) {
                            $lowest[$current] = $discovered[$next];
                        }

                        continue;
                    }

                    $depth--;
                    $above = $parent[$current];

                    if ($above === -1) {
                        continue;
                    }

                    if ($lowest[$current] < $lowest[$above]) {
                        $lowest[$above] = $lowest[$current];
                    }
                    if ($above !== $root && $lowest[$current] >= $discovered[$above]) {
                        $isChokepoint[$above] = true;
                    }
                }

                if ($rootChildren > 1) {
                    $isChokepoint[$root] = true;
                }
            }
        }

        return [$components, count($isChokepoint)];
    }
}
