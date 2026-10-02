<?php

namespace App\Support;

/**
 * Cells reserved before the search starts: long entries the template must contain.
 */
readonly class SlotPlan
{
    /**
     * @param  array<int, int>  $locked  Padded cell index => fixed value (white or block), twins included
     * @param  list<int>  $lengths  Reserved entry lengths, longest slot first
     * @param  int  $fullWidthEntries  Reserved entries that span the whole grid
     * @param  list<string>  $placements  Human-readable description of each reserved slot
     */
    public function __construct(
        public array $locked = [],
        public array $lengths = [],
        public int $fullWidthEntries = 0,
        public array $placements = [],
    ) {}
}
