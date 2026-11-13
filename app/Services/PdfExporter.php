<?php

namespace App\Services;

use App\Enums\PdfLayout;
use App\Models\Crossword;
use Barryvdh\DomPDF\Facade\Pdf;
use CrosswordBuilder\CrosswordIO\GridNumberer;

class PdfExporter
{
    public function __construct(private readonly GridNumberer $numberer, private readonly PdfWrapLayout $wrapLayout, private readonly PdfMetaAnswerBlanks $metaAnswerBlanks) {}

    /**
     * @param  'portrait'|'landscape'  $orientation
     * @return string The raw PDF binary content.
     */
    public function export(Crossword $crossword, bool $includeSolution = true, string $orientation = 'portrait', ?string $narrative = null, ?string $imagePath = null, PdfLayout $layout = PdfLayout::Standard): string
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

        // The meta answer's blanks go under the grid, as on the solve page; the standard layout centres them on the page.
        $metaAnswerPattern = $crossword->metaAnswerPattern();
        $metaAnswer = null;
        $metaAnswerHeight = 0.0;
        if ($metaAnswerPattern !== null) {
            $contentWidth = ($pageWidth - 2 * $margin) * 72;
            $blankLines = $this->metaAnswerBlanks->lines($metaAnswerPattern, $contentWidth);
            if ($blankLines !== []) {
                $metaAnswer = ['prompt' => $crossword->meta_answer_prompt, 'lines' => $blankLines, 'width' => $contentWidth];
                $metaAnswerHeight = (12 + (filled($crossword->meta_answer_prompt) ? 20 : 0) + $this->metaAnswerBlanks->height(count($blankLines))) / 72;
            }
        }

        $maxGridWidth = $pageWidth - 2 * $margin;
        $maxGridHeight = $pageHeight - 2 * $margin - 1.5 - $metaAnswerHeight;
        $cellSize = round(min(0.33, $maxGridWidth / $crossword->width, $maxGridHeight / $crossword->height), 3);

        $cluesAcross = $crossword->clues_across ?? [];
        $cluesDown = $crossword->clues_down ?? [];

        $plan = null;
        if ($layout->wrapsCluesAroundGrid()) {
            $plan = $this->wrapLayout->plan(
                $layout,
                ($pageWidth - 2 * $margin) * 72,
                ($pageHeight - 2 * $margin) * 72,
                $crossword->width,
                $crossword->height,
                [
                    'title' => $crossword->displayTitle(),
                    'author' => $crossword->author,
                    'copyright' => $crossword->copyright,
                    'notes' => $crossword->notes,
                    'narrative' => $narrative,
                    'imagePath' => $imagePath,
                ],
                $cluesAcross,
                $cluesDown,
                $metaAnswerPattern === null ? null : ['pattern' => $metaAnswerPattern, 'prompt' => $crossword->meta_answer_prompt],
            );
            $cellSize = round($plan['cellSize'] / 72, 4);
        }

        $numberFontSize = round(max(4, $cellSize * 18), 1);
        $letterFontSize = round(max(6, $cellSize * 28), 1);
        $numberHeight = round($cellSize * 0.35, 3);

        $forceCluePageBreak = $this->shouldBreakBeforeClues(
            $crossword->height,
            $cellSize,
            count($cluesAcross),
            count($cluesDown),
            $pageHeight - 2 * $margin,
            $metaAnswerHeight,
        );

        $imageDataUri = null;
        if ($imagePath && file_exists($imagePath)) {
            $mime = mime_content_type($imagePath) ?: 'image/png';
            $imageDataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($imagePath));
        }

        $pdf = Pdf::loadView($plan ? 'exports.crossword-pdf-wrap' : 'exports.crossword-pdf', [
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
            'plan' => $plan,
            'metaAnswer' => $metaAnswer,
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
    public function shouldBreakBeforeClues(int $gridHeight, float $cellSize, int $acrossCount, int $downCount, float $availableHeight = 9.5, float $metaAnswerHeight = 0.0): bool
    {
        $headerHeight = 0.5;
        $gridTotalHeight = $gridHeight * $cellSize + 0.17 + $metaAnswerHeight; // grid + header margin + the blanks under it

        // Each clue section: heading (~0.5") + ceil(clueCount / 2) rows at ~0.20" each
        $clueLineHeight = 0.20;
        $sectionHeaderHeight = 0.5;
        $acrossHeight = $acrossCount > 0 ? $sectionHeaderHeight + ceil($acrossCount / 2) * $clueLineHeight : 0;
        $downHeight = $downCount > 0 ? $sectionHeaderHeight + ceil($downCount / 2) * $clueLineHeight : 0;

        $totalHeight = $headerHeight + $gridTotalHeight + $acrossHeight + $downHeight;

        return $totalHeight > $availableHeight;
    }
}
