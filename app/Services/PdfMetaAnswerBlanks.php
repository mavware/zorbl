<?php

namespace App\Services;

/**
 * Lays out a meta answer's blanks for a PDF: one underscored cell per letter,
 * punctuation kept, lines broken only between words.
 *
 * Every cell has a fixed width, so the lines are decided here by arithmetic
 * and the view draws each one as a table of known size. All lengths are in
 * points.
 */
class PdfMetaAnswerBlanks
{
    public const float SLOT_HEIGHT = 16.0;

    private const float SLOT_WIDTH = 14.0;

    private const float LITERAL_WIDTH = 7.0;

    /** Space between two cells of the same word. */
    private const float CELL_GAP = 4.0;

    private const float WORD_GAP = 18.0;

    public const float LINE_SPACING = 6.0;

    /**
     * @param  string  $pattern  The answer's shape: `_` per letter or digit, spaces and punctuation kept.
     * @return list<array{width: float, cells: list<array{type: 'slot'|'literal'|'gap', width: float, char?: string}>}>
     */
    public function lines(string $pattern, float $width): array
    {
        $lines = [];
        $cells = [];
        $lineWidth = 0.0;

        $endLine = function () use (&$lines, &$cells, &$lineWidth): void {
            if ($cells !== []) {
                $lines[] = ['width' => $lineWidth, 'cells' => $cells];
            }
            $cells = [];
            $lineWidth = 0.0;
        };

        foreach (preg_split('/\s+/u', trim($pattern), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $characters = mb_str_split($word);

            if ($cells !== [] && $lineWidth + self::WORD_GAP + $this->wordWidth($characters) > $width) {
                $endLine();
            }

            foreach ($characters as $index => $character) {
                $cellWidth = $character === '_' ? self::SLOT_WIDTH : self::LITERAL_WIDTH;
                $gap = match (true) {
                    $cells === [] => 0.0,
                    $index === 0 => self::WORD_GAP,
                    default => self::CELL_GAP,
                };

                // A single word wider than the line is broken wherever it runs out of room.
                if ($cells !== [] && $lineWidth + $gap + $cellWidth > $width) {
                    $endLine();
                    $gap = 0.0;
                }

                if ($gap > 0) {
                    $cells[] = ['type' => 'gap', 'width' => $gap];
                }

                $cells[] = $character === '_'
                    ? ['type' => 'slot', 'width' => $cellWidth]
                    : ['type' => 'literal', 'width' => $cellWidth, 'char' => $character];
                $lineWidth += $gap + $cellWidth;
            }
        }

        $endLine();

        return $lines;
    }

    /** The height the view gives `$lineCount` lines of blanks. */
    public function height(int $lineCount): float
    {
        return $lineCount * (self::SLOT_HEIGHT + self::LINE_SPACING);
    }

    /**
     * @param  list<string>  $characters
     */
    private function wordWidth(array $characters): float
    {
        $slots = count(array_keys($characters, '_', true));

        return $slots * self::SLOT_WIDTH
            + (count($characters) - $slots) * self::LITERAL_WIDTH
            + (count($characters) - 1) * self::CELL_GAP;
    }
}
