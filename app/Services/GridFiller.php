<?php

namespace App\Services;

use App\Models\Word;
use CrosswordBuilder\CrosswordIO\GridNumberer;
use Random\Engine\Mt19937;
use Random\Randomizer;

class GridFiller
{
    /** @var array<string, list<string>> Pattern → candidate words cache */
    private array $candidateCache = [];

    /** @var array<string, true> Words already placed in the grid (keyed by uppercase word) */
    private array $usedWords = [];

    private float $deadline;

    private bool $timedOut = false;

    private ?Randomizer $randomizer = null;

    public function __construct(
        private GridNumberer $numberer,
    ) {}

    /**
     * Fill empty slots in the grid using backtracking with constraint propagation.
     *
     * @param  array<int, array<int, mixed>>  $grid  Numbered grid
     * @param  array<int, array<int, string>>  $solution  Current solution letters
     * @param  array<string, array{bars?: list<string>}>  $styles
     * @param  int|null  $seed  When non-null, randomizes candidate order and slot tie-breaks for this run. Different seeds produce different valid fills; null keeps the deterministic "highest score first" behavior.
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string, timed_out: bool}
     */
    public function fill(
        array $grid,
        array $solution,
        int $width,
        int $height,
        array $styles = [],
        int $minLength = 3,
        int $timeout = 10,
        ?int $seed = null,
    ): array {
        $this->deadline = microtime(true) + $timeout;
        $this->timedOut = false;
        $this->usedWords = [];
        $this->randomizer = $seed !== null ? new Randomizer(new Mt19937($seed)) : null;

        $numbered = $this->numberer->number($grid, $width, $height, $styles, $minLength);

        // Build slot list with current patterns, and track pre-filled words
        $slots = [];
        foreach (['across', 'down'] as $dir) {
            foreach ($numbered[$dir] as $slot) {
                $pattern = self::getPattern($solution, $slot, $dir);
                if (str_contains($pattern, '_')) {
                    $slots[] = [
                        'direction' => $dir,
                        'number' => $slot['number'],
                        'row' => $slot['row'],
                        'col' => $slot['col'],
                        'length' => $slot['length'],
                        'pattern' => $pattern,
                    ];
                } else {
                    // Track fully pre-filled words to prevent duplicates
                    $this->usedWords[$pattern] = true;
                }
            }
        }

        if (empty($slots)) {
            return [
                'success' => true,
                'fills' => [],
                'message' => 'Grid is already fully filled.',
                'timed_out' => false,
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
                'timed_out' => false,
            ];
        }

        // Return partial results if we got any
        if (! empty($fills)) {
            return [
                'success' => false,
                'fills' => $fills,
                'message' => $this->timedOut
                    ? 'Ran out of time after filling '.count($fills).' '.str('word')->plural(count($fills)).'. Try filling some letters manually first.'
                    : 'Partially filled '.count($fills).' '.str('word')->plural(count($fills)).'. Some slots could not be filled.',
                'timed_out' => $this->timedOut,
            ];
        }

        return [
            'success' => false,
            'fills' => [],
            'message' => $this->timedOut
                ? 'Ran out of time before finding a fill. Try filling some letters manually first.'
                : 'Could not find valid words to fill the grid. Try filling some letters manually first.',
            'timed_out' => $this->timedOut,
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
        if ($this->hasTimedOut()) {
            return false;
        }

        // Rebuild patterns from current solution state
        $unfilled = [];
        foreach ($slots as $slot) {
            $pattern = self::getPattern($solution, $slot, $slot['direction']);
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
            if ($this->hasTimedOut()) {
                return false;
            }

            $candidates = array_values(array_filter(
                $this->getCandidates($slot['pattern'], $slot['length']),
                fn (string $w) => ! isset($this->usedWords[$w]),
            ));
            if (empty($candidates)) {
                return false; // Dead end — no candidates for this slot
            }
            if ($this->randomizer !== null && count($candidates) > 1) {
                $candidates = $this->randomizer->shuffleArray($candidates);
            }
            $slot['candidates'] = $candidates;
            $withCandidates[] = $slot;
        }

        // Pre-shuffle so the stable usort below produces random tie-breaks between equally-constrained slots.
        if ($this->randomizer !== null && count($withCandidates) > 1) {
            $withCandidates = $this->randomizer->shuffleArray($withCandidates);
        }

        usort($withCandidates, fn ($a, $b) => count($a['candidates']) <=> count($b['candidates']));

        // Try filling the most constrained slot
        $target = $withCandidates[0];

        foreach ($target['candidates'] as $word) {
            // Once the deadline passes, stop trying alternatives at every level of
            // the stack — each one costs a database query per open crossing slot.
            if ($this->hasTimedOut()) {
                return false;
            }

            // Place word in solution
            $newSolution = $solution;
            $this->placeWord($newSolution, $target, $word);

            // Mark word as used
            $this->usedWords[$word] = true;

            // Check all crossing slots still have candidates, and that any slot this
            // placement completed through crossings spells a real, unused word.
            $completedByCrossing = $this->isConsistent($unfilled, $newSolution, $target);

            if ($completedByCrossing !== false) {
                $fills[] = [
                    'direction' => $target['direction'],
                    'number' => $target['number'],
                    'word' => $word,
                ];

                foreach ($completedByCrossing as $completed) {
                    $this->usedWords[$completed['word']] = true;
                    $fills[] = $completed;
                }

                if ($this->backtrack($slots, $newSolution, $width, $height, $grid, $styles, $minLength, $fills)) {
                    // Update solution for caller
                    foreach ($newSolution as $r => $row) {
                        foreach ($row as $c => $val) {
                            $solution[$r][$c] = $val;
                        }
                    }

                    return true;
                }

                foreach ($completedByCrossing as $completed) {
                    unset($this->usedWords[$completed['word']]);
                    array_pop($fills);
                }
                array_pop($fills);
            }

            // Backtrack — release word for reuse in other slots
            unset($this->usedWords[$word]);
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
     * Check that placing a word doesn't leave any open slot with zero candidates,
     * and that every slot it completed through crossings is a real, unused word.
     *
     * Slots pre-filled by the user never reach here (they're excluded up front),
     * so a fully-lettered slot in this list was spelled by the solver's own
     * crossings and must be validated like any other placement.
     *
     * @param  list<array{direction: string, number: int, row: int, col: int, length: int}>  $openSlots  Slots that still had blanks before this placement
     * @param  array<int, array<int, string>>  $solution
     * @param  array{direction: string, number: int}  $placed
     * @return list<array{direction: string, number: int, word: string}>|false Words completed by crossing, or false when inconsistent
     */
    private function isConsistent(array $openSlots, array $solution, array $placed): array|false
    {
        $completed = [];
        $completedWords = [];

        foreach ($openSlots as $slot) {
            if ($slot['direction'] === $placed['direction'] && $slot['number'] === $placed['number']) {
                continue;
            }

            if ($this->hasTimedOut()) {
                return false;
            }

            $pattern = self::getPattern($solution, $slot, $slot['direction']);

            if (! str_contains($pattern, '_')) {
                if (! $this->isWord($pattern) || isset($this->usedWords[$pattern]) || isset($completedWords[$pattern])) {
                    return false;
                }

                $completedWords[$pattern] = true;
                $completed[] = [
                    'direction' => $slot['direction'],
                    'number' => $slot['number'],
                    'word' => $pattern,
                ];

                continue;
            }

            $candidates = array_filter(
                $this->getCandidates($pattern, $slot['length']),
                fn (string $w) => ! isset($this->usedWords[$w]),
            );
            if (empty($candidates)) {
                return false;
            }
        }

        return $completed;
    }

    /**
     * Whether the current fill has exceeded its deadline. Latches once tripped so
     * every pending loop unwinds without re-checking the clock.
     */
    private function hasTimedOut(): bool
    {
        if (! $this->timedOut && microtime(true) > $this->deadline) {
            $this->timedOut = true;
        }

        return $this->timedOut;
    }

    private function isWord(string $word): bool
    {
        return in_array($word, $this->getCandidates($word, strlen($word)), true);
    }

    /**
     * Get candidate words for a pattern, using cache.
     *
     * The cache is intentionally kept across fill() calls on the same instance:
     * candidates depend only on the pattern and the dictionary, so retries with a
     * different seed on the same grid skip most of their database queries.
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
     * Extract the current letter pattern for a slot from the solution grid.
     *
     * @param  array<int, array<int, string|null>>  $solution
     * @param  array{row: int, col: int, length: int}  $slot
     */
    public static function getPattern(array $solution, array $slot, string $direction): string
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
