<?php

namespace Zorbl\CrosswordIO\Exporters;

use Zorbl\CrosswordIO\Crossword;

class IpuzExporter
{
    /**
     * Validate that the crossword can be exported to .ipuz without data loss.
     *
     * iPUZ supports all features, so this is a no-op.
     */
    public function validate(Crossword $crossword): void
    {
        // iPUZ supports all crossword features — nothing to validate.
    }

    /**
     * Export a Crossword to an ipuz-format array.
     *
     * @return array<string, mixed>
     */
    public function export(Crossword $crossword, bool $allowLossyExport = false): array
    {
        $puzzle = $this->buildPuzzleGrid($crossword);
        $solution = $this->buildSolutionGrid($crossword);
        $clues = $this->buildClues($crossword);

        $ipuz = [
            'version' => 'https://ipuz.org/v2',
            'kind' => [$crossword->kind],
            'dimensions' => [
                'width' => $crossword->width,
                'height' => $crossword->height,
            ],
            'puzzle' => $puzzle,
            'solution' => $solution,
            'clues' => $clues,
        ];

        if ($crossword->title) {
            $ipuz['title'] = $crossword->title;
        }

        if ($crossword->author) {
            $ipuz['author'] = $crossword->author;
        }

        if ($crossword->copyright) {
            $ipuz['copyright'] = $crossword->copyright;
        }

        if ($crossword->notes) {
            $ipuz['notes'] = $crossword->notes;
        }

        if ($crossword->metadata) {
            $ipuz = array_merge($ipuz, $crossword->metadata);
        }

        return $ipuz;
    }

    /**
     * Export to JSON string.
     */
    public function toJson(Crossword $crossword, bool $allowLossyExport = false): string
    {
        return json_encode($this->export($crossword), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Build the puzzle grid, re-applying styles as dict cells.
     *
     * @return array<int, array<int, mixed>>
     */
    private function buildPuzzleGrid(Crossword $crossword): array
    {
        $grid = $crossword->grid;
        $styles = $crossword->styles ?? [];
        $puzzle = [];

        for ($row = 0; $row < $crossword->height; $row++) {
            $puzzleRow = [];

            for ($col = 0; $col < $crossword->width; $col++) {
                $cellValue = $grid[$row][$col];
                $styleKey = "$row,$col";

                if (isset($styles[$styleKey])) {
                    $cell = ['cell' => $cellValue];
                    $cell['style'] = $styles[$styleKey];
                    $puzzleRow[] = $cell;
                } else {
                    $puzzleRow[] = $cellValue;
                }
            }

            $puzzle[] = $puzzleRow;
        }

        return $puzzle;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildSolutionGrid(Crossword $crossword): array
    {
        return $crossword->solution;
    }

    /**
     * @return array{Across: array, Down: array}
     */
    private function buildClues(Crossword $crossword): array
    {
        return [
            'Across' => array_map(
                fn (array $clue) => [$clue['number'], $clue['clue']],
                $crossword->clues_across
            ),
            'Down' => array_map(
                fn (array $clue) => [$clue['number'], $clue['clue']],
                $crossword->clues_down
            ),
        ];
    }
}
