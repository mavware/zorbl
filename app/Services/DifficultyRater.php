<?php

namespace App\Services;

use App\Models\Crossword;

class DifficultyRater
{
    /**
     * Difficulty levels mapped from score ranges.
     */
    public const string EASY = 'Easy';

    public const string MEDIUM = 'Medium';

    public const string HARD = 'Hard';

    public const string EXPERT = 'Expert';

    /**
     * Calculate a difficulty score (1.0–5.0) for a crossword puzzle.
     *
     * Factors:
     * - Grid size (larger = harder)
     * - Black cell density (fewer blocks = harder)
     * - Average word length (longer words = harder)
     * - Average solve time from completions (when available)
     *
     * @return array{score: float, label: string, factors: array<string, float>}
     */
    public function rate(Crossword $crossword, ?float $avgSolveTime = null): array
    {
        $factors = [];

        // Factor 1: Grid size (1.0–2.0)
        $totalCells = $crossword->width * $crossword->height;
        $sizeFactor = $this->normalize($totalCells, 9, 625, 1.0, 2.0);
        $factors['size'] = round($sizeFactor, 2);

        // Factor 2: Block density — fewer blocks = harder (1.0–1.5)
        $grid = $crossword->grid ?? [];
        $blockCount = 0;
        $fillableCells = 0;

        foreach ($grid as $row) {
            foreach ($row as $cell) {
                if ($cell === '#') {
                    $blockCount++;
                } elseif ($cell !== null) {
                    $fillableCells++;
                }
            }
        }

        $blockRatio = $totalCells > 0 ? $blockCount / $totalCells : 0;
        // A standard crossword is ~17% blocks; fewer blocks = harder
        $densityFactor = $this->normalize(1 - $blockRatio, 0.5, 1.0, 1.0, 1.5);
        $factors['density'] = round($densityFactor, 2);

        // Factor 3: Average word length (1.0–1.5)
        $wordLengths = $this->computeWordLengths($crossword);
        $avgWordLength = count($wordLengths) > 0 ? array_sum($wordLengths) / count($wordLengths) : 3;
        $lengthFactor = $this->normalize($avgWordLength, 3, 10, 1.0, 1.5);
        $factors['word_length'] = round($lengthFactor, 2);

        // Base score from structural factors
        $score = ($sizeFactor * 0.4) + ($densityFactor * 0.3) + ($lengthFactor * 0.3);

        // Factor 4: Empirical solve time (if available, overrides up to 40% of score)
        if ($avgSolveTime !== null && $avgSolveTime > 0) {
            // Normalize solve time: 60s = very easy, 3600s = very hard
            $timeFactor = $this->normalize($avgSolveTime, 60, 3600, 1.0, 5.0);
            $factors['solve_time'] = round($timeFactor, 2);

            // Blend: 60% structural + 40% empirical
            $score = ($score * 0.6) + ($timeFactor * 0.4);
        }

        $score = round(max(1.0, min(5.0, $score)), 1);

        return [
            'score' => $score,
            'label' => $this->scoreToLabel($score),
            'factors' => $factors,
        ];
    }

    /**
     * Map a numeric difficulty score to a human-readable label.
     */
    public function scoreToLabel(float $score): string
    {
        return match (true) {
            $score < 2.0 => self::EASY,
            $score < 3.0 => self::MEDIUM,
            $score < 4.0 => self::HARD,
            default => self::EXPERT,
        };
    }

    /**
     * Linearly interpolate a value from one range to another.
     */
    private function normalize(float $value, float $inMin, float $inMax, float $outMin, float $outMax): float
    {
        $clamped = max($inMin, min($inMax, $value));
        $ratio = ($inMax - $inMin) > 0 ? ($clamped - $inMin) / ($inMax - $inMin) : 0;

        return $outMin + ($ratio * ($outMax - $outMin));
    }

    /**
     * Compute the length of every word (across and down) in the grid.
     *
     * @return array<int, int>
     */
    private function computeWordLengths(Crossword $crossword): array
    {
        $lengths = [];
        $grid = $crossword->grid ?? [];
        $width = $crossword->width;
        $height = $crossword->height;

        // Across words
        for ($row = 0; $row < $height; $row++) {
            $len = 0;

            for ($col = 0; $col < $width; $col++) {
                $cell = $grid[$row][$col] ?? null;

                if ($cell !== '#' && $cell !== null) {
                    $len++;
                } else {
                    if ($len >= 2) {
                        $lengths[] = $len;
                    }
                    $len = 0;
                }
            }

            if ($len >= 2) {
                $lengths[] = $len;
            }
        }

        // Down words
        for ($col = 0; $col < $width; $col++) {
            $len = 0;

            for ($row = 0; $row < $height; $row++) {
                $cell = $grid[$row][$col] ?? null;

                if ($cell !== '#' && $cell !== null) {
                    $len++;
                } else {
                    if ($len >= 2) {
                        $lengths[] = $len;
                    }
                    $len = 0;
                }
            }

            if ($len >= 2) {
                $lengths[] = $len;
            }
        }

        return $lengths;
    }
}
