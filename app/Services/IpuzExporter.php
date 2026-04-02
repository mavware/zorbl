<?php

namespace App\Services;

use App\Models\Crossword;

class IpuzExporter
{
    /**
     * Export a Crossword model to an ipuz-format array.
     *
     * @return array<string, mixed>
     */
    public function export(Crossword $crossword): array
    {
        $puzzle = $this->buildPuzzleGrid($crossword);
        $solution = $this->buildSolutionGrid($crossword);
        $clues = $this->buildClues($crossword);

        $ipuz = [
            'version' => 'http://ipuz.org/v2',
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
    public function toJson(Crossword $crossword): string
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
                $styleKey = "{$row},{$col}";

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
            'Across' => collect($crossword->clues_across)->map(fn (array $clue) => [$clue['number'], $clue['clue']])->values()->all(),
            'Down' => collect($crossword->clues_down)->map(fn (array $clue) => [$clue['number'], $clue['clue']])->values()->all(),
        ];
    }
}
