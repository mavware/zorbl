<?php

namespace App\Services;

use App\Models\Crossword;
use App\Models\Template;
use Illuminate\Database\Eloquent\Collection;
use CrosswordBuilder\CrosswordIO\GridNumberer;

/**
 * Finds a 15x15 grid template from the database whose across/down slots can
 * accommodate a given set of words, allowing words to intersect where their
 * letters agree at the shared cell.
 */
class ThemeWordPlacer
{
    private const int SIZE = 15;

    public function __construct(private GridNumberer $numberer) {}

    /**
     * Search active 15x15 templates for one that fits every supplied word.
     *
     * Words are normalized to letters-only uppercase. Each word occupies one
     * across or down slot of exactly its length; two placed words may share a
     * cell only when they carry the same letter there.
     *
     * @param  list<string>  $words
     * @return array{
     *     template: Template,
     *     grid: array<int, array<int, mixed>>,
     *     styles: array<string, array{bars?: list<string>}>|null,
     *     solution: array<int, array<int, string|null>>,
     *     across: array<int, array{number: int, row: int, col: int, length: int}>,
     *     down: array<int, array{number: int, row: int, col: int, length: int}>,
     *     placements: list<array{word: string, direction: string, number: int, row: int, col: int, length: int}>
     * }|null Null when no template can hold all of the words.
     */
    public function place(array $words): ?array
    {
        $words = $this->normalizeWords($words);

        if ($words === []) {
            return null;
        }

        // Longest words first — they have the fewest candidate slots, so
        // committing them early prunes the search fastest.
        usort($words, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($this->candidateTemplates() as $template) {
            $placement = $this->fitInto($template, $words);

            if ($placement !== null) {
                return $placement;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Template>
     */
    private function candidateTemplates()
    {
        return Template::query()
            ->where('is_active', true)
            ->where('width', self::SIZE)
            ->where('height', self::SIZE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Attempt to place every word into a single template's slots.
     *
     * @param  list<string>  $words
     * @return array<string, mixed>|null
     */
    private function fitInto(Template $template, array $words): ?array
    {
        $styles = $template->styles;
        $minLength = max(2, $template->min_word_length);

        $numbered = $this->numberer->number($template->grid, self::SIZE, self::SIZE, $styles ?? [], $minLength);

        $slots = [];
        foreach (['across', 'down'] as $direction) {
            foreach ($numbered[$direction] as $slot) {
                $slots[] = [
                    'direction' => $direction,
                    'number' => $slot['number'],
                    'row' => $slot['row'],
                    'col' => $slot['col'],
                    'length' => $slot['length'],
                ];
            }
        }

        $solution = $this->blankSolution($numbered['grid']);
        $usedSlots = [];
        $placements = [];

        if (! $this->assign($words, 0, $slots, $solution, $usedSlots, $placements)) {
            return null;
        }

        return [
            'template' => $template,
            'grid' => $numbered['grid'],
            'styles' => $styles,
            'solution' => $solution,
            'across' => $numbered['across'],
            'down' => $numbered['down'],
            'placements' => $placements,
        ];
    }

    /**
     * Recursively assign words to slots via backtracking.
     *
     * @param  list<string>  $words
     * @param  list<array{direction: string, number: int, row: int, col: int, length: int}>  $slots
     * @param  array<int, array<int, string|null>>  $solution
     * @param  array<string, true>  $usedSlots
     * @param  list<array{word: string, direction: string, number: int, row: int, col: int, length: int}>  $placements
     */
    private function assign(array $words, int $index, array $slots, array &$solution, array &$usedSlots, array &$placements): bool
    {
        if ($index >= count($words)) {
            return true;
        }

        $word = $words[$index];
        $length = strlen($word);

        foreach ($slots as $slot) {
            $key = $slot['direction'].':'.$slot['number'];

            if ($slot['length'] !== $length || isset($usedSlots[$key])) {
                continue;
            }

            $restore = $this->tryPlace($solution, $slot, $word);

            if ($restore === null) {
                continue;
            }

            $usedSlots[$key] = true;
            $placements[] = [
                'word' => $word,
                'direction' => $slot['direction'],
                'number' => $slot['number'],
                'row' => $slot['row'],
                'col' => $slot['col'],
                'length' => $slot['length'],
            ];

            if ($this->assign($words, $index + 1, $slots, $solution, $usedSlots, $placements)) {
                return true;
            }

            array_pop($placements);
            unset($usedSlots[$key]);
            $this->undoPlace($solution, $slot, $restore);
        }

        return false;
    }

    /**
     * Write a word into a slot when every crossing cell is empty or already
     * carries the same letter. Returns the prior cell values for undo, or null
     * on conflict (nothing is written on conflict).
     *
     * @param  array<int, array<int, string|null>>  $solution
     * @param  array{direction: string, row: int, col: int, length: int}  $slot
     * @return list<string|null>|null
     */
    private function tryPlace(array &$solution, array $slot, string $word): ?array
    {
        $cells = $this->slotCells($slot);
        $previous = [];

        foreach ($cells as $i => [$r, $c]) {
            $existing = $solution[$r][$c];

            if ($existing !== '' && $existing !== $word[$i]) {
                return null;
            }

            $previous[$i] = $existing;
        }

        foreach ($cells as $i => [$r, $c]) {
            $solution[$r][$c] = $word[$i];
        }

        return $previous;
    }

    /**
     * @param  array<int, array<int, string|null>>  $solution
     * @param  array{direction: string, row: int, col: int, length: int}  $slot
     * @param  list<string|null>  $previous
     */
    private function undoPlace(array &$solution, array $slot, array $previous): void
    {
        foreach ($this->slotCells($slot) as $i => [$r, $c]) {
            $solution[$r][$c] = $previous[$i];
        }
    }

    /**
     * @param  array{direction: string, row: int, col: int, length: int}  $slot
     * @return list<array{int, int}>
     */
    private function slotCells(array $slot): array
    {
        $cells = [];

        for ($i = 0; $i < $slot['length']; $i++) {
            $r = $slot['direction'] === 'across' ? $slot['row'] : $slot['row'] + $i;
            $c = $slot['direction'] === 'across' ? $slot['col'] + $i : $slot['col'];
            $cells[] = [$r, $c];
        }

        return $cells;
    }

    /**
     * Build a blank letter grid: '#' for blocks, null for voids, '' elsewhere.
     *
     * @param  array<int, array<int, mixed>>  $grid
     * @return array<int, array<int, string|null>>
     */
    private function blankSolution(array $grid): array
    {
        $solution = Crossword::emptySolution(self::SIZE, self::SIZE);

        foreach ($grid as $r => $row) {
            foreach ($row as $c => $cell) {
                if ($cell === null) {
                    $solution[$r][$c] = null;
                } elseif ($cell === '#') {
                    $solution[$r][$c] = '#';
                }
            }
        }

        return $solution;
    }

    /**
     * Normalize entries to letters-only uppercase, dropping any that are empty.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function normalizeWords(array $words): array
    {
        $normalized = [];

        foreach ($words as $word) {
            $clean = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $word));

            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        return $normalized;
    }
}
