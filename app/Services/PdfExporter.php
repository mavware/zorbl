<?php

namespace App\Services;

use App\Models\Crossword;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Zorbl\CrosswordIO\GridNumberer;

class PdfExporter
{
    public function __construct(private readonly GridNumberer $numberer) {}

    /**
     * @param  'portrait'|'landscape'  $orientation
     * @return string The raw PDF binary content.
     */
    public function export(Crossword $crossword, bool $includeSolution = true, string $orientation = 'portrait', ?string $narrative = null, ?string $imagePath = null): string
    {
        $result = $this->numberer->number(
            $crossword->grid,
            $crossword->width,
            $crossword->height,
            $crossword->styles ?? [],
        );

        $isLandscape = $orientation === 'landscape';
        $pageWidth = $isLandscape ? 11.0 : 8.5;
        $pageHeight = $isLandscape ? 8.5 : 11.0;
        $margin = 0.75;

        $maxGridWidth = $pageWidth - 2 * $margin;
        $maxGridHeight = $pageHeight - 2 * $margin - 1.5;
        $cellSize = round(min(0.33, $maxGridWidth / $crossword->width, $maxGridHeight / $crossword->height), 3);

        $numberFontSize = round(max(4, $cellSize * 18), 1);
        $letterFontSize = round(max(6, $cellSize * 28), 1);
        $numberHeight = round($cellSize * 0.35, 3);

        $cluesAcross = $crossword->clues_across ?? [];
        $cluesDown = $crossword->clues_down ?? [];

        $forceCluePageBreak = $this->shouldBreakBeforeClues(
            $crossword->height,
            $cellSize,
            count($cluesAcross),
            count($cluesDown),
            $pageHeight - 2 * $margin,
        );

        $imageDataUri = null;
        if ($imagePath && file_exists($imagePath)) {
            $mime = mime_content_type($imagePath) ?: 'image/png';
            $imageDataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($imagePath));
        }

        $pdf = Pdf::loadView('exports.crossword-pdf', [
            'title' => $crossword->displayTitle(),
            'author' => $crossword->author,
            'copyright' => $crossword->copyright,
            'notes' => $crossword->notes,
            'narrative' => $narrative,
            'imageDataUri' => $imageDataUri,
            'numberedGrid' => $result['grid'],
            'solution' => $crossword->solution,
            'prefilled' => $crossword->prefilled,
            'cluesAcross' => $cluesAcross,
            'cluesDown' => $cluesDown,
            'styles' => $crossword->styles ?? [],
            'includeSolution' => $includeSolution,
            'cellSize' => $cellSize,
            'numberFontSize' => $numberFontSize,
            'letterFontSize' => $letterFontSize,
            'numberHeight' => $numberHeight,
            'forceCluePageBreak' => $forceCluePageBreak,
            'orientation' => $orientation,
        ]);

        $pdf->setPaper('letter', $orientation);

        return $pdf->output();
    }

    /**
     * @param  Collection<int, Crossword>  $crosswords
     * @param  'portrait'|'landscape'  $orientation
     * @return string The raw PDF binary content.
     */
    public function exportBatch(Collection $crosswords, string $orientation = 'portrait', ?string $collectionTitle = null): string
    {
        $isLandscape = $orientation === 'landscape';
        $pageWidth = $isLandscape ? 11.0 : 8.5;
        $pageHeight = $isLandscape ? 8.5 : 11.0;
        $margin = 0.75;

        $puzzles = [];
        foreach ($crosswords as $crossword) {
            $result = $this->numberer->number(
                $crossword->grid,
                $crossword->width,
                $crossword->height,
                $crossword->styles ?? [],
            );

            $maxGridWidth = $pageWidth - 2 * $margin;
            $maxGridHeight = $pageHeight - 2 * $margin - 1.5;
            $cellSize = round(min(0.33, $maxGridWidth / $crossword->width, $maxGridHeight / $crossword->height), 3);

            $numberFontSize = round(max(4, $cellSize * 18), 1);
            $letterFontSize = round(max(6, $cellSize * 28), 1);
            $numberHeight = round($cellSize * 0.35, 3);

            $cluesAcross = $crossword->clues_across ?? [];
            $cluesDown = $crossword->clues_down ?? [];

            $forceCluePageBreak = $this->shouldBreakBeforeClues(
                $crossword->height,
                $cellSize,
                count($cluesAcross),
                count($cluesDown),
                $pageHeight - 2 * $margin,
            );

            $puzzles[] = [
                'title' => $crossword->displayTitle(),
                'author' => $crossword->author,
                'copyright' => $crossword->copyright,
                'notes' => $crossword->notes,
                'numberedGrid' => $result['grid'],
                'solution' => $crossword->solution,
                'prefilled' => $crossword->prefilled,
                'cluesAcross' => $cluesAcross,
                'cluesDown' => $cluesDown,
                'styles' => $crossword->styles ?? [],
                'cellSize' => $cellSize,
                'numberFontSize' => $numberFontSize,
                'letterFontSize' => $letterFontSize,
                'numberHeight' => $numberHeight,
                'forceCluePageBreak' => $forceCluePageBreak,
            ];
        }

        $pdf = Pdf::loadView('exports.crossword-batch-pdf', [
            'puzzles' => $puzzles,
            'collectionTitle' => $collectionTitle,
            'orientation' => $orientation,
        ]);

        $pdf->setPaper('letter', $orientation);

        return $pdf->output();
    }

    /**
     * Determine whether clues should be forced onto a separate page.
     *
     * For small puzzles, grid and clues fit together on one page.
     * For larger puzzles, a page break prevents awkward mid-clue splits.
     */
    public function shouldBreakBeforeClues(int $gridHeight, float $cellSize, int $acrossCount, int $downCount, float $availableHeight = 9.5): bool
    {
        $headerHeight = 0.5;
        $gridTotalHeight = $gridHeight * $cellSize + 0.17; // grid + header margin

        // Each clue section: heading (~0.5") + ceil(clueCount / 2) rows at ~0.20" each
        $clueLineHeight = 0.20;
        $sectionHeaderHeight = 0.5;
        $acrossHeight = $acrossCount > 0 ? $sectionHeaderHeight + ceil($acrossCount / 2) * $clueLineHeight : 0;
        $downHeight = $downCount > 0 ? $sectionHeaderHeight + ceil($downCount / 2) * $clueLineHeight : 0;

        $totalHeight = $headerHeight + $gridTotalHeight + $acrossHeight + $downHeight;

        return $totalHeight > $availableHeight;
    }
}
