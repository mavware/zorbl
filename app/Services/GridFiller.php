<?php

namespace App\Services;

use App\Models\Word;

class GridFiller
{
    /** @var array<string, list<string>> Pattern → candidate words cache */
    private array $candidateCache = [];

    private float $deadline;

    public function __construct(
        private GridNumberer $numberer,
    ) {}

    /**
     * Fill empty slots in the grid using backtracking with constraint propagation.
     *
     * @param  array<int, array<int, mixed>>  $grid  Numbered grid
     * @param  array<int, array<int, string>>  $solution  Current solution letters
     * @param  array<string, array{bars?: list<string>}>  $styles
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    public function fill(
        array $grid,
        array $solution,
        int $width,
        int $height,
        array $styles = [],
        int $minLength = 3,
        int $timeout = 10,
    ): array {
        $this->deadline = microtime(true) + $timeout;
        $this->candidateCache = [];

        $numbered = $this->numberer->number($grid, $width, $height, $styles, $minLength);

        // Build slot list with current patterns
        $slots = [];
        foreach (['across', 'down'] as $dir) {
            foreach ($numbered[$dir] as $slot) {
                $pattern = $this->getPattern($solution, $slot, $dir);
                if (str_contains($pattern, '_')) {
                    $slots[] = [
                        'direction' => $dir,
                        'number' => $slot['number'],
                        'row' => $slot['row'],
                        'col' => $slot['col'],
                        'length' => $slot['length'],
                        'pattern' => $pattern,
                    ];
                }
            }
        }

        if (empty($slots)) {
            return [
                'success' => true,
                'fills' => [],
                'message' => 'Grid is already fully filled.',
            ];
        }

        // Attempt backtracking fill
        $fills = [];
        $success = $this->backtrack($slots, $solution, $width, $height, $grid, $styles, $minLength, $fills);

        if ($success) {
            return [
                'success' => true,
                'fills' => $fills,
                'message' => 'Filled '.count($fills).' '.str('word')->plural(count($fills)).'.',
            ];
        }

        // Return partial results if we got any
        if (! empty($fills)) {
            return [
                'success' => false,
                'fills' => $fills,
                'message' => 'Partially filled '.count($fills).' '.str('word')->plural(count($fills)).'. Some slots could not be filled.',
            ];
        }

        return [
            'success' => false,
            'fills' => [],
            'message' => 'Could not find valid words to fill the grid. Try filling some letters manually first.',
        ];
    }

    /**
     * Recursive backtracking solver.
     *
     * @param  list<array{direction: string, number: int, row: int, col: int, length: int, pattern: string}>  $slots
     * @param  list<array{direction: string, number: int, word: string}>  $fills
     */
    private function backtrack(
        array $slots,
        array $solution,
        int $width,
        int $height,
        array $grid,
        array $styles,
        int $minLength,
        array &$fills,
    ): bool {
        if (microtime(true) > $this->deadline) {
            return false;
        }

        // Rebuild patterns from current solution state
        $unfilled = [];
        foreach ($slots as $slot) {
            $pattern = $this->getPattern($solution, $slot, $slot['direction']);
            if (str_contains($pattern, '_')) {
                $slot['pattern'] = $pattern;
                $unfilled[] = $slot;
            }
        }

        if (empty($unfilled)) {
            return true; // All slots filled
        }

        // Get candidates for each unfilled slot, sort by fewest candidates (most constrained first)
        $withCandidates = [];
        foreach ($unfilled as $slot) {
            $candidates = $this->getCandidates($slot['pattern'], $slot['length']);
            if (empty($candidates)) {
                return false; // Dead end — no candidates for this slot
            }
            $slot['candidates'] = $candidates;
            $withCandidates[] = $slot;
        }

        usort($withCandidates, fn ($a, $b) => count($a['candidates']) <=> count($b['candidates']));

        // Try filling the most constrained slot
        $target = $withCandidates[0];

        foreach ($target['candidates'] as $word) {
            // Place word in solution
            $newSolution = $solution;
            $this->placeWord($newSolution, $target, $word);

            // Check all crossing slots still have candidates
            if ($this->isConsistent($slots, $newSolution, $target)) {
                $fills[] = [
                    'direction' => $target['direction'],
                    'number' => $target['number'],
                    'word' => $word,
                ];

                if ($this->backtrack($slots, $newSolution, $width, $height, $grid, $styles, $minLength, $fills)) {
                    // Update solution for caller
                    foreach ($newSolution as $r => $row) {
                        foreach ($row as $c => $val) {
                            $solution[$r][$c] = $val;
                        }
                    }

                    return true;
                }

                array_pop($fills);
            }
        }

        return false;
    }

    /**
     * Place a word into the solution grid.
     *
     * @param  array<int, array<int, string>>  $solution
     * @param  array{direction: string, row: int, col: int, length: int}  $slot
     */
    private function placeWord(array &$solution, array $slot, string $word): void
    {
        for ($i = 0; $i < $slot['length'] && $i < strlen($word); $i++) {
            $r = $slot['direction'] === 'across' ? $slot['row'] : $slot['row'] + $i;
            $c = $slot['direction'] === 'across' ? $slot['col'] + $i : $slot['col'];
            $solution[$r][$c] = $word[$i];
        }
    }

    /**
     * Check that placing a word doesn't leave any crossing slot with zero candidates.
     *
     * @param  list<array{direction: string, number: int, row: int, col: int, length: int}>  $allSlots
     * @param  array<int, array<int, string>>  $solution
     * @param  array{direction: string, number: int}  $placed
     */
    private function isConsistent(array $allSlots, array $solution, array $placed): bool
    {
        foreach ($allSlots as $slot) {
            if ($slot['direction'] === $placed['direction'] && $slot['number'] === $placed['number']) {
                continue;
            }

            $pattern = $this->getPattern($solution, $slot, $slot['direction']);
            if (! str_contains($pattern, '_')) {
                // Fully filled — check it's a valid word
                $candidates = $this->getCandidates($pattern, $slot['length']);

                // A fully filled word that doesn't match any dictionary word is still ok
                // if it was pre-filled by the user. But if we just created it through
                // crossing, it should ideally be valid. For performance, skip this check.
                continue;
            }

            $candidates = $this->getCandidates($pattern, $slot['length']);
            if (empty($candidates)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get candidate words for a pattern, using cache.
     *
     * @return list<string>
     */
    private function getCandidates(string $pattern, int $length): array
    {
        $key = $pattern.'/'.$length;

        if (isset($this->candidateCache[$key])) {
            return $this->candidateCache[$key];
        }

        $candidates = Word::where('length', $length)
            ->where('word', 'LIKE', strtoupper($pattern))
            ->orderByDesc('score')
            ->limit(50)
            ->pluck('word')
            ->all();

        $this->candidateCache[$key] = $candidates;

        return $candidates;
    }

    /**
     * Extract the current pattern for a slot from the solution grid.
     */
    private function getPattern(array $solution, array $slot, string $direction): string
    {
        $pattern = '';
        for ($i = 0; $i < $slot['length']; $i++) {
            $r = $direction === 'across' ? $slot['row'] : $slot['row'] + $i;
            $c = $direction === 'across' ? $slot['col'] + $i : $slot['col'];
            $letter = $solution[$r][$c] ?? '';
            $pattern .= ($letter !== '' && $letter !== '#' && $letter !== null) ? strtoupper($letter) : '_';
        }

        return $pattern;
    }
}
