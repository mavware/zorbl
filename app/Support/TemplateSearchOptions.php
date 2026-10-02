<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Controls for the simulated-annealing search that places the blocks.
 */
readonly class TemplateSearchOptions
{
    public function __construct(
        /** Annealing steps per grid cell; total steps are this times size squared. */
        public int $iterationsPerCell = 150,
        public float $startTemperature = 8.0,
        public float $endTemperature = 0.1,
        /** Independent runs per requested template; the best ones are kept. */
        public int $attemptsPerTemplate = 2,
        /** Share of blocks two results may have in common before one is dropped as a near-duplicate. */
        public float $maxSharedBlocks = 0.7,
        /** Relative frequency of flipping a single cell. */
        public float $flipWeight = 45.0,
        /** Relative frequency of painting a 2-3 cell run of blocks. */
        public float $paintWeight = 20.0,
        /** Relative frequency of clearing a 2-3 cell run. */
        public float $eraseWeight = 10.0,
        /** Relative frequency of sliding a block one cell. */
        public float $slideWeight = 25.0,
    ) {
        if ($iterationsPerCell < 1 || $attemptsPerTemplate < 1) {
            throw new InvalidArgumentException('Iterations per cell and attempts per template must be at least 1.');
        }

        if ($endTemperature <= 0 || $startTemperature < $endTemperature) {
            throw new InvalidArgumentException('Temperatures must be positive and the start temperature must not be below the end temperature.');
        }

        if ($maxSharedBlocks <= 0 || $maxSharedBlocks > 1) {
            throw new InvalidArgumentException('The near-duplicate threshold must be above 0% and at most 100%.');
        }

        if (min($flipWeight, $paintWeight, $eraseWeight, $slideWeight) < 0 || $flipWeight + $paintWeight + $eraseWeight + $slideWeight <= 0) {
            throw new InvalidArgumentException('Move weights cannot be negative and at least one must be above zero.');
        }
    }
}
