<?php

namespace App\Support;

use App\Enums\TemplateGeneratorStyle;
use InvalidArgumentException;

/**
 * Size- and style-scaled targets a generated template is scored against.
 *
 * Bands for 15x15 and 21x21 come from published grids (word limits of 78 and
 * 140, block density around 17%). Other sizes are interpolated from those two
 * anchors; sizes below 11 follow the curated 5x5, 7x7 and 9x9 template sets.
 */
readonly class TemplateTargets
{
    public const int MIN_SIZE = 5;

    public const int MAX_SIZE = 35;

    /** Longest entry the word list can fill; the default longest entry, not a hard limit. */
    public const int MAX_ENTRY_LENGTH = 21;

    /** Published maximum word counts, pinned where the fitted curve is off by a word or two. */
    private const array PUBLISHED_MAX_WORDS = [15 => 78, 21 => 140, 23 => 168];

    /**
     * @param  array<int, float>  $lengthShares  Target share of entries by length, keyed 3-8 where 8 means eight or longer
     */
    public function __construct(
        public int $size,
        public TemplateGeneratorStyle $style,
        public int $minBlocks,
        public int $maxBlocks,
        public int $minWords,
        public int $maxWords,
        public array $lengthShares,
        public int $maxBlackRun,
        public int $maxEntryLength,
        public int $openWindowSize,
        public int $maxFullWidthEntries,
        public int $maxCheaters,
        public float $maxBorderShare,
        public int $minEntryLength = 3,
        public int $maxBlackShape = 4,
        public int $minTouchingBlocks = 1,
        public TemplateScoringWeights $weights = new TemplateScoringWeights,
    ) {}

    /**
     * @param  int  $reservedFullWidthEntries  Full-width entries deliberately reserved as theme slots
     */
    public static function for(int $size, TemplateGeneratorStyle $style, int $reservedFullWidthEntries = 0): self
    {
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
            throw new InvalidArgumentException(sprintf(
                'Templates can be generated for sizes %d to %d, got %d.',
                self::MIN_SIZE,
                self::MAX_SIZE,
                $size,
            ));
        }

        if ($size < $style->minimumSize()) {
            throw new InvalidArgumentException(sprintf(
                '%s templates need a grid of at least %d×%d.',
                $style->label(),
                $style->minimumSize(),
                $style->minimumSize(),
            ));
        }

        $cells = $size * $size;
        $wordLimit = self::maxWordCount($size);
        $themeless = $style === TemplateGeneratorStyle::Themeless;

        [$minDensity, $maxDensity] = match (true) {
            $themeless => [0.11, 0.155],
            $size <= 6 => [0.08, 0.24],
            $size <= 8 => [0.10, 0.27],
            $size <= 10 => [0.12, 0.25],
            $size <= 12 => [0.13, 0.20],
            default => [0.15, 0.185],
        };

        [$minWordShare, $maxWordShare] = match (true) {
            $themeless => [0.82, 0.92],
            $size <= 10 => [0.65, 0.95],
            $size <= 12 => [0.85, 1.0],
            default => [0.90, 1.0],
        };

        return new self(
            size: $size,
            style: $style,
            minBlocks: (int) ceil($minDensity * $cells),
            maxBlocks: (int) floor($maxDensity * $cells),
            minWords: (int) ceil($minWordShare * $wordLimit),
            maxWords: (int) floor($maxWordShare * $wordLimit),
            lengthShares: self::lengthShares($size, $themeless),
            maxBlackRun: $size <= 15 ? 4 : 5,
            maxEntryLength: min($size, self::MAX_ENTRY_LENGTH),
            openWindowSize: match (true) {
                $themeless, $size <= 8 => 6,
                default => 5,
            },
            maxFullWidthEntries: match (true) {
                $size <= 9 => PHP_INT_MAX,
                $themeless => 2,
                default => $reservedFullWidthEntries,
            },
            // Curated minis rely on corner blocks, which count as cheaters.
            maxCheaters: $size <= 9 ? 4 : 0,
            // Published grids put blocks on the border in proportion to the
            // border's share of the grid; allow a quarter more than that.
            maxBorderShare: 1.25 * (4 * ($size - 1)) / $cells,
        );
    }

    /**
     * Copy of these targets with some values replaced.
     *
     * @param  array<string, mixed>  $overrides  Keyed by property name; `lengthShares` may list only the lengths to change and is rescaled to total 100%
     */
    public function with(array $overrides): self
    {
        $values = get_object_vars($this);
        $unknown = array_diff(array_keys($overrides), array_diff(array_keys($values), ['size', 'style']));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown template setting: '.implode(', ', $unknown).'.');
        }

        if (isset($overrides['lengthShares'])) {
            $shares = array_replace($this->lengthShares, array_intersect_key($overrides['lengthShares'], $this->lengthShares));
            $total = array_sum($shares);

            if ($total <= 0 || min($shares) < 0) {
                throw new InvalidArgumentException('Entry-length shares cannot be negative and must not all be zero.');
            }

            $overrides['lengthShares'] = array_map(fn (int|float $share): float => $share / $total, $shares);
        }

        $targets = new self(...array_replace($values, $overrides));

        if ($targets->minBlocks < 0 || $targets->minBlocks > $targets->maxBlocks) {
            throw new InvalidArgumentException('Minimum block density cannot be negative or above the maximum.');
        }

        if ($targets->minWords < 0 || $targets->minWords > $targets->maxWords) {
            throw new InvalidArgumentException('Minimum word count cannot be negative or above the maximum.');
        }

        if ($targets->minEntryLength < 2 || $targets->minEntryLength > $targets->maxEntryLength || $targets->maxEntryLength > $targets->size) {
            throw new InvalidArgumentException(sprintf(
                'Entry lengths must run from at least 2 up to the grid size of %d, with the shortest no longer than the longest.',
                $targets->size,
            ));
        }

        if ($targets->maxBlackRun < 1 || $targets->maxBlackShape < 1 || $targets->openWindowSize < 2) {
            throw new InvalidArgumentException('Block run and block shape limits must be at least 1, and the open-area limit at least 2.');
        }

        if ($targets->maxFullWidthEntries < 0 || $targets->maxCheaters < 0 || $targets->maxBorderShare < 0 || $targets->maxBorderShare > 1) {
            throw new InvalidArgumentException('Allowances cannot be negative and the border share must be between 0% and 100%.');
        }

        // Three or more cannot be met alongside the other block rules.
        if ($targets->minTouchingBlocks < 0 || $targets->minTouchingBlocks > 2) {
            throw new InvalidArgumentException('Minimum touching blocks must be between 0 and 2.');
        }

        return $targets;
    }

    /**
     * Maximum word count for a square grid, fitted through the published
     * 15x15 (78) and 21x21 (140) limits.
     */
    public static function maxWordCount(int $size): int
    {
        return self::PUBLISHED_MAX_WORDS[$size]
            ?? 2 * (int) round((0.2444 * $size * $size + 1.533 * $size) / 2);
    }

    /**
     * @return array<int, float>
     */
    private static function lengthShares(int $size, bool $themeless): array
    {
        return match (true) {
            $themeless => [3 => 0.157, 4 => 0.266, 5 => 0.180, 6 => 0.114, 7 => 0.133, 8 => 0.150],
            $size <= 6 => [3 => 0.40, 4 => 0.30, 5 => 0.30, 6 => 0.0, 7 => 0.0, 8 => 0.0],
            $size <= 8 => [3 => 0.257, 4 => 0.206, 5 => 0.196, 6 => 0.121, 7 => 0.220, 8 => 0.0],
            $size <= 10 => [3 => 0.260, 4 => 0.357, 5 => 0.158, 6 => 0.051, 7 => 0.071, 8 => 0.103],
            // No published or curated set at 11-12: midway between the 9x9 and 15x15 mixes.
            $size <= 12 => [3 => 0.22, 4 => 0.35, 5 => 0.20, 6 => 0.07, 7 => 0.06, 8 => 0.10],
            $size <= 16 => [3 => 0.184, 4 => 0.344, 5 => 0.238, 6 => 0.093, 7 => 0.054, 8 => 0.087],
            default => [3 => 0.155, 4 => 0.297, 5 => 0.262, 6 => 0.135, 7 => 0.052, 8 => 0.099],
        };
    }
}
