<?php

namespace App\Services;

use App\Support\SlotPlan;
use App\Support\TemplateTargets;
use InvalidArgumentException;

/**
 * Reserves rotationally symmetric slots for entries a template must hold.
 *
 * A slot flush against one edge is mirrored flush against the opposite edge
 * in the twin line, so two entries of the same length share one slot pair.
 * On odd sizes the centre line can hold one self-symmetric slot.
 */
class TemplateSlotPlanner
{
    private const string ACROSS = 'across';

    private const string DOWN = 'down';

    /** Reserved lines closer together than this count as stacked and are avoided when possible. */
    private const int PREFERRED_GAP = 3;

    /**
     * Reserve a slot for each word, across words in rows and down words in columns.
     *
     * @param  list<array{word: string, direction?: string}>  $words
     */
    public function forWords(int $size, array $words): SlotPlan
    {
        $byDirection = [self::ACROSS => [], self::DOWN => []];

        foreach ($words as $entry) {
            $word = strtoupper(preg_replace('/[^A-Za-z]/', '', $entry['word'] ?? '') ?? '');
            $direction = strtolower($entry['direction'] ?? self::ACROSS);

            if ($word === '') {
                continue;
            }

            if (! isset($byDirection[$direction])) {
                throw new InvalidArgumentException("Direction for {$word} must be across or down.");
            }

            $this->assertPlaceable($size, $word);
            $byDirection[$direction][] = $word;
        }

        $plan = new SlotPlan;

        foreach ($byDirection as $direction => $directionWords) {
            if ($directionWords === []) {
                continue;
            }

            $plan = $this->merge($plan, $this->planDirection($size, $direction, $directionWords));
        }

        return $plan;
    }

    /**
     * Reserve across slots of the given lengths in the classic theme rows: row 4,
     * row 8 on 19x19 and larger, then the centre row on odd sizes.
     *
     * @param  list<int>  $lengths  Slot lengths from the top row down; missing ones use defaults
     */
    public function forThemeLengths(int $size, array $lengths): SlotPlan
    {
        $longest = min($size, TemplateTargets::MAX_ENTRY_LENGTH);
        $lengths = array_values($lengths);
        $edgeRows = $size >= 19 ? [3 => 'start', 7 => 'end'] : [3 => 'start'];
        $slots = [];
        $position = 0;

        foreach ($edgeRows as $row => $side) {
            $length = $lengths[$position++] ?? min($size - 4, $longest);

            if ($length > $longest || ($length !== $size && ($length < 3 || $length > $size - 4))) {
                throw new InvalidArgumentException(sprintf(
                    'A theme slot on row %d of a %d×%d grid must be %d letters or between 3 and %d, and no longer than %d.',
                    $row + 1, $size, $size, $size, $size - 4, $longest,
                ));
            }

            $slots[] = ['line' => $row, 'length' => $length, 'side' => $side, 'label' => "{$length}-letter slot"];
        }

        if ($size % 2 === 1) {
            $length = $lengths[$position] ?? ($size <= $longest ? $size : $size - 8);

            if ($length > $longest || ! $this->fitsCentred($size, $length)) {
                throw new InvalidArgumentException(sprintf(
                    'The centre theme slot of a %d×%d grid must be %d letters or leave at least 4 squares on each side, and be no longer than %d.',
                    $size, $size, $size, $longest,
                ));
            }

            $slots[] = ['line' => intdiv($size, 2), 'length' => $length, 'side' => 'centre', 'label' => "{$length}-letter slot"];
        }

        return $this->build($size, self::ACROSS, $slots);
    }

    /**
     * @param  list<string>  $words
     */
    private function planDirection(int $size, string $direction, array $words): SlotPlan
    {
        usort($words, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $mid = intdiv($size, 2);
        $slots = [];

        // The longest word that can sit on the centre line takes it.
        if ($size % 2 === 1) {
            foreach ($words as $i => $word) {
                if ($this->fitsCentred($size, strlen($word))) {
                    $slots[] = ['line' => $mid, 'length' => strlen($word), 'side' => 'centre', 'label' => $word];
                    unset($words[$i]);
                    break;
                }
            }
        }

        // Words of equal length share a slot pair: one flush to each edge.
        $pairs = [];
        foreach ($words as $word) {
            $length = strlen($word);

            if (! $this->fitsFlush($size, $length)) {
                throw new InvalidArgumentException(sprintf(
                    '%s (%d letters) cannot be placed in a %d×%d grid. Words of up to %d letters or exactly %d letters fit.',
                    $word, $length, $size, $size, $size - 4, $size,
                ));
            }

            $open = array_search(true, array_map(fn (array $pair): bool => $pair['length'] === $length && $pair['twin'] === null, $pairs), true);

            if ($open !== false) {
                $pairs[$open]['twin'] = $word;
            } else {
                $pairs[] = ['length' => $length, 'word' => $word, 'twin' => null];
            }
        }

        $available = range(0, $mid - 1);

        if (count($pairs) > count($available)) {
            throw new InvalidArgumentException(sprintf(
                'Too many %s words for a %d×%d grid: at most %d can be placed.',
                $direction, $size, $size, count($available) * 2 + ($size % 2),
            ));
        }

        $lines = $this->chooseLines($size, $available, count($pairs), $slots !== []);

        // Longer words sit nearer the centre; alternate the side for a staggered look.
        foreach ($pairs as $i => $pair) {
            $slots[] = [
                'line' => $lines[$i],
                'length' => $pair['length'],
                'side' => $i % 2 === 0 ? 'start' : 'end',
                'label' => $pair['twin'] === null ? $pair['word'] : "{$pair['word']} and {$pair['twin']}",
            ];
        }

        return $this->build($size, $direction, $slots);
    }

    /**
     * Pick the rows (or columns) for the slot pairs: as far apart as possible,
     * then as close to the classic third row as possible.
     *
     * @param  list<int>  $available
     * @return list<int> One line per pair, nearest the centre first
     */
    private function chooseLines(int $size, array $available, int $count, bool $centreUsed): array
    {
        if ($count === 0) {
            return [];
        }

        $mid = intdiv($size, 2);
        $best = null;
        $bestScore = null;

        foreach ($this->combinations($available, $count) as $lines) {
            $occupied = $lines;
            foreach ($lines as $line) {
                $occupied[] = $size - 1 - $line;
            }
            if ($centreUsed) {
                $occupied[] = $mid;
            }
            sort($occupied);

            $gap = PHP_INT_MAX;
            for ($i = 1; $i < count($occupied); $i++) {
                $gap = min($gap, $occupied[$i] - $occupied[$i - 1]);
            }

            $edgePenalty = 0;
            $drift = 0;
            foreach ($lines as $line) {
                $edgePenalty += max(0, 3 - $line) * 2;
                $drift += abs($line - 3);
            }

            $score = [min($gap, self::PREFERRED_GAP), -$edgePenalty, -$drift];

            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = $lines;
            }
        }

        rsort($best);

        return $best;
    }

    /**
     * @param  list<int>  $items
     * @return iterable<list<int>>
     */
    private function combinations(array $items, int $count, int $from = 0): iterable
    {
        if ($count === 0) {
            yield [];

            return;
        }

        for ($i = $from; $i <= count($items) - $count; $i++) {
            foreach ($this->combinations($items, $count - 1, $i + 1) as $rest) {
                yield [$items[$i], ...$rest];
            }
        }
    }

    /**
     * Lock the cells of each slot (and its twin) white and its end caps black.
     *
     * @param  list<array{line: int, length: int, side: string, label: string}>  $slots
     */
    private function build(int $size, string $direction, array $slots): SlotPlan
    {
        $stride = $size + 2;
        $last = $stride * $stride - 1;
        $locked = [];
        $owners = [];
        $lengths = [];
        $fullWidth = 0;
        $placements = [];
        $noun = $direction === self::ACROSS ? 'row' : 'column';

        $lock = function (int $line, int $position, int $value, string $label) use (&$locked, &$owners, $stride, $last, $direction): void {
            $index = $direction === self::ACROSS
                ? ($line + 1) * $stride + $position + 1
                : ($position + 1) * $stride + $line + 1;

            foreach ([$index, $last - $index] as $cell) {
                if (isset($locked[$cell]) && $locked[$cell] !== $value) {
                    throw new InvalidArgumentException("{$label} overlaps {$owners[$cell]}; move one of them or change its direction.");
                }

                $locked[$cell] = $value;
                $owners[$cell] = $label;
            }
        };

        foreach ($slots as $slot) {
            $length = $slot['length'];
            $start = match ($slot['side']) {
                'start' => 0,
                'end' => $size - $length,
                default => intdiv($size - $length, 2),
            };

            for ($position = $start; $position < $start + $length; $position++) {
                $lock($slot['line'], $position, TemplateGridScorer::WHITE, $slot['label']);
            }

            if ($length === $size) {
                $fullWidth += $slot['side'] === 'centre' ? 1 : 2;
            } else {
                if ($start > 0) {
                    $lock($slot['line'], $start - 1, TemplateGridScorer::BLOCK, $slot['label']);
                }
                if ($start + $length < $size) {
                    $lock($slot['line'], $start + $length, TemplateGridScorer::BLOCK, $slot['label']);
                }
            }

            $lengths[] = $length;
            $placements[] = sprintf('%s %s %s %d', $slot['label'], $direction, $noun, $slot['line'] + 1);
        }

        return new SlotPlan($locked, $lengths, $fullWidth, $placements);
    }

    private function merge(SlotPlan $a, SlotPlan $b): SlotPlan
    {
        foreach ($b->locked as $cell => $value) {
            if (isset($a->locked[$cell]) && $a->locked[$cell] !== $value) {
                throw new InvalidArgumentException('An across word and a down word overlap where one needs a block; change one of them.');
            }
        }

        return new SlotPlan(
            $a->locked + $b->locked,
            [...$a->lengths, ...$b->lengths],
            $a->fullWidthEntries + $b->fullWidthEntries,
            [...$a->placements, ...$b->placements],
        );
    }

    private function assertPlaceable(int $size, string $word): void
    {
        $length = strlen($word);

        if (! $this->fitsFlush($size, $length) && ! ($size % 2 === 1 && $this->fitsCentred($size, $length))) {
            throw new InvalidArgumentException(sprintf(
                '%s (%d letters) cannot be placed in a %d×%d grid. Words of up to %d letters or exactly %d letters fit.',
                $word, $length, $size, $size, $size - 4, $size,
            ));
        }
    }

    private function fitsFlush(int $size, int $length): bool
    {
        return $length === $size || ($length >= 3 && $length <= $size - 4);
    }

    private function fitsCentred(int $size, int $length): bool
    {
        return $length === $size || ($length >= 3 && ($size - $length) % 2 === 0 && intdiv($size - $length, 2) >= 4);
    }
}
