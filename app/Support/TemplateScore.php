<?php

namespace App\Support;

readonly class TemplateScore
{
    /**
     * @param  list<string>  $hardViolations  Broken construction rules; empty for a usable template
     * @param  array<int, int>  $lengthCounts  Entry count keyed by entry length
     */
    public function __construct(
        public float $energy,
        public array $hardViolations,
        public int $blockCount,
        public float $blockDensity,
        public int $wordCount,
        public float $averageLength,
        public array $lengthCounts,
        public float $threeLetterShare,
        public float $lengthDistance,
        public int $largestOpenSquare,
        public int $openWindowCount,
        public int $cheaterCount,
        public int $chokepointCount,
        public int $largestBlackShape,
        public int $fullWidthEntryCount,
        public float $borderShare,
        public int $lonelyBlockCount = 0,
        public int $shortEntryCount = 0,
        public int $longEntryCount = 0,
    ) {}

    public function isValid(): bool
    {
        return $this->hardViolations === [];
    }
}
