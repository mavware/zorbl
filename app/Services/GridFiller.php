<?php

namespace App\Services;

use App\Models\Word;
use CrosswordBuilder\CrosswordIO\GridNumberer;
use Random\Engine\Mt19937;
use Random\Randomizer;

class GridFiller
{
    /**
     * Candidates scoring at least this are tried before any that score lower,
     * so poor fill is only reached for when nothing acceptable fits.
     */
    public const float QUALITY_THRESHOLD = Word::SCORE_ACCEPTABLE;

    /**
     * Score gap that makes a candidate e times likelier to be tried first
     * within its tier. Smaller values follow the scores more strictly.
     */
    private const float SCORE_TEMPERATURE = 10.0;

    private const int MAX_CANDIDATES = 50;

    /**
     * Spreads ties in score across the alphabet when picking the candidates
     * for a pattern: a fixed, portable scatter of the row id. Without it the
     * candidates for an open slot would be the alphabetically first words at
     * the top score, however the run is seeded.
     */
    private const string TIE_BREAK_SQL = '(id * 2654435761) % 1000003';

    /** @var array<string, array<string, float>> Pattern → candidate word → score, best first */
    private array $candidateCache = [];

    /** @var array<string, true> Words already placed in the grid (keyed by uppercase word) */
    private array $usedWords = [];

    /** @var array<string, float> Score of every word the solver has placed */
    private array $placedScores = [];

    private float $deadline;

    private bool $timedOut = false;

    private float $minScore = 0.0;

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
     * @param  int|null  $seed  When non-null, randomizes candidate order (weighted by score) and slot tie-breaks for this run. Different seeds produce different valid fills; null keeps the deterministic "highest score first" behavior.
     * @param  float  $minScore  Only words scoring at least this are used, including for words the fill completes through crossings. QUALITY_THRESHOLD keeps the fill to acceptable words; 0 allows the whole dictionary.
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, score: float, message: string, timed_out: bool} `score` is the average word score of the fills, 0 when there are none.
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
        float $minScore = 0.0,
    ): array {
        $this->deadline = microtime(true) + $timeout;
        $this->timedOut = false;
        $this->usedWords = [];
        $this->placedScores = [];
        $this->minScore = $minScore;
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
                'score' => 0.0,
                'message' => 'Grid is already fully filled.',
                'timed_out' => false,
            ];
        }

        // Attempt backtracking fill
        $fills = [];
        $success = $this->backtrack($slots, $solution, $width, $height, $grid, $styles, $minLength, $fills);
        $score = $this->averageScore($fills);

        if ($success) {
            return [
                'success' => true,
                'fills' => $fills,
                'score' => $score,
                'message' => 'Filled '.count($fills).' '.str('word')->plural(count($fills)).'.',
                'timed_out' => false,
            ];
        }

        // Return partial results if we got any
        if (! empty($fills)) {
            return [
                'success' => false,
                'fills' => $fills,
                'score' => $score,
                'message' => $this->timedOut
                    ? 'Ran out of time after filling '.count($fills).' '.str('word')->plural(count($fills)).'. Try filling some letters manually first.'
                    : 'Partially filled '.count($fills).' '.str('word')->plural(count($fills)).'. Some slots could not be filled.',
                'timed_out' => $this->timedOut,
            ];
        }

        return [
            'success' => false,
            'fills' => [],
            'score' => 0.0,
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

            $candidates = $this->unusedCandidates($slot['pattern'], $slot['length']);
            if (empty($candidates)) {
                return false; // Dead end — no candidates for this slot
            }
            if ($this->randomizer !== null && count($candidates) > 1) {
                $candidates = $this->orderByScore($candidates);
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

        foreach ($target['candidates'] as $word => $score) {
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
            $this->placedScores[$word] = $score;

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
     * Order candidates so acceptable words come before poor ones, and within
     * each tier higher-scoring words tend to come first without the order
     * being fixed: each word draws a random key weighted by its score
     * (Efraimidis–Spirakis), so different seeds still explore different fills.
     *
     * @param  array<string, float>  $candidates  word → score
     * @return array<string, float>
     */
    private function orderByScore(array $candidates): array
    {
        $keys = [];

        foreach ($candidates as $word => $score) {
            $weight = exp($score / self::SCORE_TEMPERATURE);
            $draw = $this->randomizer->nextFloat();

            $keys[$word] = [
                $score >= self::QUALITY_THRESHOLD ? 1 : 0,
                $draw > 0 ? log($draw) / $weight : -INF,
            ];
        }

        uksort($candidates, fn (string $a, string $b): int => $keys[$b] <=> $keys[$a]);

        return $candidates;
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
                $score = $this->wordScore($pattern);

                if ($score === null || isset($this->usedWords[$pattern]) || isset($completedWords[$pattern])) {
                    return false;
                }

                $completedWords[$pattern] = true;
                $this->placedScores[$pattern] = $score;
                $completed[] = [
                    'direction' => $slot['direction'],
                    'number' => $slot['number'],
                    'word' => $pattern,
                ];

                continue;
            }

            if (empty($this->unusedCandidates($pattern, $slot['length']))) {
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

    /**
     * The word's score, or null when it isn't in the dictionary.
     */
    private function wordScore(string $word): ?float
    {
        return $this->getCandidates($word, strlen($word))[$word] ?? null;
    }

    /**
     * @param  list<array{word: string}>  $fills
     */
    private function averageScore(array $fills): float
    {
        if ($fills === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($fills as $fill) {
            $total += $this->placedScores[$fill['word']] ?? 0.0;
        }

        return round($total / count($fills), 1);
    }

    /**
     * Candidates for a pattern that aren't already placed in the grid.
     *
     * @return array<string, float> word → score, best first
     */
    private function unusedCandidates(string $pattern, int $length): array
    {
        return array_filter(
            $this->getCandidates($pattern, $length),
            fn (string $word): bool => ! isset($this->usedWords[$word]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Get candidate words for a pattern, using cache.
     *
     * The cache is intentionally kept across fill() calls on the same instance:
     * candidates depend only on the pattern, the score floor and the dictionary,
     * so retries with a different seed on the same grid skip most of their
     * database queries.
     *
     * @return array<string, float> word → score, best first
     */
    private function getCandidates(string $pattern, int $length): array
    {
        $key = $pattern.'/'.$length.'/'.$this->minScore;

        if (isset($this->candidateCache[$key])) {
            return $this->candidateCache[$key];
        }

        $candidates = Word::where('length', $length)
            ->where('word', 'LIKE', strtoupper($pattern))
            ->when($this->minScore > 0, fn ($query) => $query->where('score', '>=', $this->minScore))
            ->orderByDesc('score')
            ->orderByRaw(self::TIE_BREAK_SQL)
            ->limit(self::MAX_CANDIDATES)
            ->pluck('score', 'word')
            ->map(fn (mixed $score): float => (float) $score)
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
