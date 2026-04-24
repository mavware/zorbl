<?php

namespace App\Services;

use App\Models\Crossword;
use Barryvdh\DomPDF\Facade\Pdf;
use Zorbl\CrosswordIO\GridNumberer;

class PdfExporter
{
    public function __construct(private readonly GridNumberer $numberer) {}

    /**
     * Export a crossword puzzle to a print-ready PDF.
     *
     * @return string The raw PDF binary content.
     */
    public function export(Crossword $crossword, bool $includeSolution = true): string
    {
        $result = $this->numberer->number(
            $crossword->grid,
            $crossword->width,
            $crossword->height,
            $crossword->styles ?? [],
        );

        $maxGridWidth = 7.0;  // inches (letter width 8.5 - 2*0.75 margins)
        $maxGridHeight = 8.5; // inches (letter height 11 - 2*0.75 margins - header)
        $cellSize = round(min(0.33, $maxGridWidth / $crossword->width, $maxGridHeight / $crossword->height), 3);

        $numberFontSize = round(max(4, $cellSize * 18), 1);
        $letterFontSize = round(max(6, $cellSize * 28), 1);
        $numberHeight = round($cellSize * 0.35, 3);

        $pdf = Pdf::loadView('exports.crossword-pdf', [
            'title' => $crossword->title ?: 'Untitled Puzzle',
            'author' => $crossword->author,
            'copyright' => $crossword->copyright,
            'numberedGrid' => $result['grid'],
            'solution' => $crossword->solution,
            'cluesAcross' => $crossword->clues_across ?? [],
            'cluesDown' => $crossword->clues_down ?? [],
            'styles' => $crossword->styles ?? [],
            'includeSolution' => $includeSolution,
            'cellSize' => $cellSize,
            'numberFontSize' => $numberFontSize,
            'letterFontSize' => $letterFontSize,
            'numberHeight' => $numberHeight,
        ]);

        $pdf->setPaper('letter');

        return $pdf->output();
    }
}
