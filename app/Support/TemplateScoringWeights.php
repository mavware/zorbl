<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Penalty weights for the soft scoring terms. A higher weight makes the
 * generator work harder to avoid that flaw at the expense of the others.
 */
readonly class TemplateScoringWeights
{
    public function __construct(
        /** Per letter by which an entry falls short of the shortest wanted length. */
        public float $shortEntry = 1000.0,
        /** Per letter by which an entry exceeds the longest wanted length. */
        public float $longEntry = 200.0,
        /** Per block outside the block-count band. */
        public float $blockBand = 4.0,
        /** Per word outside the word-count band. */
        public float $wordBand = 3.0,
        /** Multiplies the distance (0-2) between the entry-length mix and its target. */
        public float $lengthMix = 150.0,
        /** Per all-white square of the open-area limit or larger. */
        public float $openArea = 15.0,
        /** Per cheater square above the allowance. */
        public float $cheater = 20.0,
        /** Per cell by which a connected black shape exceeds the limit. */
        public float $blackShape = 6.0,
        /** Per white cell whose removal would split the grid. */
        public float $chokepoint = 10.0,
        /** Per full-width entry above the allowance. */
        public float $fullWidth = 25.0,
        /** Per border block above the allowed share. */
        public float $border = 5.0,
        /** Per run of blocks at the maximum run length. */
        public float $longBlackRun = 12.0,
    ) {
        foreach (get_object_vars($this) as $name => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException("Scoring weight {$name} cannot be negative.");
            }
        }
    }
}
