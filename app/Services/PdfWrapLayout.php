<?php

namespace App\Services;

use App\Enums\PdfLayout;
use Closure;

/**
 * Plans a PDF page where the clues flow through three columns around a grid
 * that sits in one corner and spans two of them.
 *
 * dompdf cannot flow text around a box or through columns, so every line
 * break and every clue's column is decided here from the font's metrics and
 * the view only places the result. All lengths are in points.
 */
class PdfWrapLayout
{
    private const float COLUMN_GAP = 14.0;

    private const float GRID_GAP = 14.0;

    private const float MAX_CELL_SIZE = 36.0;

    private const float MAX_IMAGE_HEIGHT = 144.0;

    private const float MIN_CELL_SIZE = 15.0;

    /** Share of the page height the grid may take when the clues run onto further pages anyway. */
    private const float OVERFLOW_GRID_SHARE = 0.6;

    /** Width held back from each line so rounding never pushes a planned line past its column. */
    private const float LINE_SAFETY = 1.0;

    /** dompdf draws each column one border wider than the cell width it is given, plus one closing border. */
    private const float GRID_BORDER = 1.0;

    /** Measured height dompdf adds to the grid table beyond its rows. */
    private const float GRID_ROW_SLACK = 4.0;

    /** Space between the grid and the meta answer beneath it. */
    private const float META_ANSWER_GAP = 14.0;

    private const float META_PROMPT_FONT_SIZE = 9.0;

    private const float META_PROMPT_LINE_HEIGHT = 12.0;

    /** Space between the meta answer's prompt and its blanks. */
    private const float META_PROMPT_SPACING = 6.0;

    /** Clue sizes, largest first, that the grid is shrunk to make room for. */
    private const array COMFORTABLE_FONT_SIZES = [10.0, 9.5, 9.0, 8.5, 8.0];

    /** Clue sizes used only when no grid size leaves room for a comfortable one. */
    private const array SMALL_FONT_SIZES = [7.5, 7.0];

    /**
     * @param  (Closure(string $text, string $fontStyle, float $fontSize): float)|null  $measureText  Width of a run of text; defaults to dompdf's Helvetica metrics.
     */
    public function __construct(private ?Closure $measureText = null, private PdfMetaAnswerBlanks $metaAnswerBlanks = new PdfMetaAnswerBlanks) {}

    /**
     * @param  array{title: string, author?: ?string, copyright?: ?string, notes?: ?string, narrative?: ?string, imagePath?: ?string}  $header
     * @param  list<array{number: int|string, clue?: ?string}>  $cluesAcross
     * @param  list<array{number: int|string, clue?: ?string}>  $cluesDown
     * @param  array{pattern: string, prompt?: ?string}|null  $metaAnswer  The blanks (and optional prompt) drawn under the grid.
     * @return array{
     *     pageWidth: float,
     *     cellSize: float,
     *     gridWidth: float,
     *     fontSize: float,
     *     lineHeight: float,
     *     clueSpacing: float,
     *     headingFontSize: float,
     *     headingSpacing: float,
     *     header: list<array{type: string, height: float, lines?: list<string>, lineHeight?: float, imageWidth?: float, imageHeight?: float}>,
     *     pages: list<array{
     *         showsHeader: bool,
     *         grid: ?array{left: float, top: float},
     *         metaAnswer: ?array{left: float, top: float, width: float, promptLines: list<string>, promptLineHeight: float, promptSpacing: float, blankLines: list<array{width: float, cells: list<array<string, mixed>>}>},
     *         regions: list<array{left: float, top: float, width: float, items: list<array{type: string, lines: list<string>, number?: string, marginTop?: float}>}>
     *     }>
     * }
     */
    public function plan(
        PdfLayout $layout,
        float $pageWidth,
        float $pageHeight,
        int $gridColumns,
        int $gridRows,
        array $header,
        array $cluesAcross,
        array $cluesDown,
        ?array $metaAnswer = null,
    ): array {
        $headerBlocks = $this->headerBlocks($header, $pageWidth);
        $headerHeight = array_sum(array_column($headerBlocks, 'height'));

        // A header that leaves less than half the page pushes the grid and clues to a page of their own.
        $headerHasOwnPage = $headerHeight > $pageHeight / 2;
        $bodyTop = $headerHasOwnPage ? 0.0 : $headerHeight;
        $bodyHeight = $pageHeight - $bodyTop;

        $columnWidth = ($pageWidth - 2 * self::COLUMN_GAP) / 3;
        $gridBoxWidth = 2 * $columnWidth + self::COLUMN_GAP;

        // The meta answer sits under the grid and is part of the box the clues flow around.
        $metaBlock = $this->metaAnswerBlock($metaAnswer, $gridBoxWidth);
        $metaHeight = $metaBlock['height'] ?? 0.0;

        $largestCellSize = min(
            self::MAX_CELL_SIZE,
            ($gridBoxWidth - self::GRID_BORDER) / $gridColumns - self::GRID_BORDER,
            ($bodyHeight - self::GRID_ROW_SLACK - $metaHeight) / $gridRows,
        );
        $fullPageRegions = $this->fullHeightRegions($columnWidth, $pageHeight);

        $itemsBySize = [];
        $attempt = function (float $cellSize, float $fontSize) use (&$itemsBySize, $layout, $columnWidth, $bodyTop, $bodyHeight, $gridRows, $cluesAcross, $cluesDown, $fullPageRegions, $metaHeight): array {
            $itemsBySize[(string) $fontSize] ??= $this->clueItems($cluesAcross, $cluesDown, $fontSize, $columnWidth);
            $gridHeight = $cellSize * $gridRows + self::GRID_ROW_SLACK + $metaHeight;

            return $this->distribute(
                $itemsBySize[(string) $fontSize],
                $this->regionsAroundGrid($layout, $columnWidth, $bodyTop, $bodyHeight, $gridHeight),
                $fullPageRegions,
                $fontSize,
            );
        };

        [$cellSize, $fontSize, $pagesOfRegions] = $this->fit(
            $largestCellSize,
            min($largestCellSize, self::OVERFLOW_GRID_SHARE * $bodyHeight / $gridRows),
            $attempt,
        );

        $gridWidth = $gridColumns * ($cellSize + self::GRID_BORDER) + self::GRID_BORDER;
        $gridHeight = $cellSize * $gridRows + self::GRID_ROW_SLACK;
        $gridLeft = $layout->gridIsOnRight() ? $pageWidth - $gridWidth : 0.0;
        $gridTop = $layout->gridIsAtBottom() ? $pageHeight - $gridHeight - $metaHeight : $bodyTop;

        $placedMetaAnswer = null;
        if ($metaBlock !== null) {
            // Centred under the grid, but kept inside the two columns the grid's box spans.
            $metaWidth = max($gridWidth, $metaBlock['contentWidth']);
            $boxLeft = $layout->gridIsOnRight() ? $pageWidth - $gridBoxWidth : 0.0;
            $metaLeft = max($boxLeft, min($gridLeft + ($gridWidth - $metaWidth) / 2, $boxLeft + $gridBoxWidth - $metaWidth));

            $placedMetaAnswer = [
                'left' => round($metaLeft, 2),
                'top' => round($gridTop + $gridHeight + self::META_ANSWER_GAP, 2),
                'width' => round($metaWidth, 2),
                'promptLines' => $metaBlock['promptLines'],
                'promptLineHeight' => self::META_PROMPT_LINE_HEIGHT,
                'promptSpacing' => self::META_PROMPT_SPACING,
                'blankLines' => $metaBlock['blankLines'],
            ];
        }

        $pages = [];
        if ($headerHasOwnPage) {
            $pages[] = ['showsHeader' => true, 'grid' => null, 'metaAnswer' => null, 'regions' => []];
        }
        foreach ($pagesOfRegions as $index => $regions) {
            $pages[] = [
                'showsHeader' => $index === 0 && ! $headerHasOwnPage,
                'grid' => $index === 0 ? ['left' => round($gridLeft, 2), 'top' => round($gridTop, 2)] : null,
                'metaAnswer' => $index === 0 ? $placedMetaAnswer : null,
                'regions' => $regions,
            ];
        }

        return [
            'pageWidth' => $pageWidth,
            'cellSize' => $cellSize,
            'gridWidth' => $gridWidth,
            'fontSize' => $fontSize,
            'lineHeight' => $this->lineHeight($fontSize),
            'clueSpacing' => $this->clueSpacing($fontSize),
            'headingFontSize' => $fontSize + 2,
            'headingSpacing' => $this->headingSpacing(),
            'header' => $headerBlocks,
            'pages' => $pages,
        ];
    }

    /**
     * The meta answer's prompt and blanks, broken into lines no wider than
     * the grid's box, with the height they take under the grid.
     *
     * @param  array{pattern: string, prompt?: ?string}|null  $metaAnswer
     * @return array{height: float, contentWidth: float, promptLines: list<string>, blankLines: list<array{width: float, cells: list<array<string, mixed>>}>}|null
     */
    private function metaAnswerBlock(?array $metaAnswer, float $width): ?array
    {
        $blankLines = $metaAnswer === null ? [] : $this->metaAnswerBlanks->lines($metaAnswer['pattern'], $width);

        if ($blankLines === []) {
            return null;
        }

        $prompt = trim((string) ($metaAnswer['prompt'] ?? ''));
        $promptLines = $prompt === '' ? [] : $this->wrap($prompt, 'normal', self::META_PROMPT_FONT_SIZE, $width);

        $lineWidths = [
            ...array_column($blankLines, 'width'),
            ...array_map(fn (string $line): float => $this->measure($line, 'normal', self::META_PROMPT_FONT_SIZE) + self::LINE_SAFETY, $promptLines),
        ];

        return [
            'height' => self::META_ANSWER_GAP
                + ($promptLines === [] ? 0.0 : count($promptLines) * self::META_PROMPT_LINE_HEIGHT + self::META_PROMPT_SPACING)
                + $this->metaAnswerBlanks->height(count($blankLines)),
            'contentWidth' => max($lineWidths),
            'promptLines' => $promptLines,
            'blankLines' => $blankLines,
        ];
    }

    /**
     * Choose the grid and clue sizes: the largest grid whose page still holds
     * every clue at a comfortable size, then at a small one. When even the
     * smallest grid cannot share its page with all the clues, the grid takes
     * `$overflowCellSize` and the clues continue on further pages.
     *
     * @param  Closure(float $cellSize, float $fontSize): list<list<array<string, mixed>>>  $attempt  Pages of filled regions for one combination.
     * @return array{float, float, list<list<array<string, mixed>>>}
     */
    private function fit(float $largestCellSize, float $overflowCellSize, Closure $attempt): array
    {
        $cellSizes = [];
        for ($scale = 1.0; $scale > 0.3; $scale -= 0.05) {
            $cellSize = floor($largestCellSize * $scale * 100) / 100;
            if ($cellSizes !== [] && $cellSize < self::MIN_CELL_SIZE) {
                break;
            }
            $cellSizes[] = $cellSize;
        }

        foreach ([self::COMFORTABLE_FONT_SIZES, self::SMALL_FONT_SIZES] as $fontSizes) {
            foreach ($cellSizes as $cellSize) {
                foreach ($fontSizes as $fontSize) {
                    $pages = $attempt($cellSize, $fontSize);
                    if (count($pages) === 1) {
                        return [$cellSize, $fontSize, $pages];
                    }
                }
            }
        }

        $fontSize = self::COMFORTABLE_FONT_SIZES[array_key_last(self::COMFORTABLE_FONT_SIZES)];

        $overflowCellSize = floor($overflowCellSize * 100) / 100;

        return [$overflowCellSize, $fontSize, $attempt($overflowCellSize, $fontSize)];
    }

    /**
     * The three clue regions on the grid's page, in reading order: a
     * full-height column beside the grid and two short ones above or below it.
     *
     * @return list<array{left: float, top: float, width: float, height: float}>
     */
    private function regionsAroundGrid(PdfLayout $layout, float $columnWidth, float $bodyTop, float $bodyHeight, float $gridHeight): array
    {
        $shortHeight = max(0.0, $bodyHeight - $gridHeight - self::GRID_GAP);
        $shortTop = $layout->gridIsAtBottom() ? $bodyTop : $bodyTop + $gridHeight + self::GRID_GAP;
        $fullColumn = $layout->gridIsOnRight() ? 0 : 2;

        $regions = [];
        foreach ([0, 1, 2] as $column) {
            $isFull = $column === $fullColumn;
            $regions[] = [
                'left' => round($column * ($columnWidth + self::COLUMN_GAP), 2),
                'top' => round($isFull ? $bodyTop : $shortTop, 2),
                'width' => $columnWidth,
                'height' => $isFull ? $bodyHeight : $shortHeight,
            ];
        }

        return $regions;
    }

    /**
     * @return list<array{left: float, top: float, width: float, height: float}>
     */
    private function fullHeightRegions(float $columnWidth, float $pageHeight): array
    {
        return array_map(fn (int $column): array => [
            'left' => round($column * ($columnWidth + self::COLUMN_GAP), 2),
            'top' => 0.0,
            'width' => $columnWidth,
            'height' => $pageHeight,
        ], [0, 1, 2]);
    }

    /**
     * The "Across" and "Down" headings and every clue, already broken into lines.
     *
     * @param  list<array{number: int|string, clue?: ?string}>  $cluesAcross
     * @param  list<array{number: int|string, clue?: ?string}>  $cluesDown
     * @return list<array{type: string, lines: list<string>, number?: string, height: float}>
     */
    private function clueItems(array $cluesAcross, array $cluesDown, float $fontSize, float $columnWidth): array
    {
        $items = [];

        foreach (['Across' => $cluesAcross, 'Down' => $cluesDown] as $heading => $clues) {
            if ($clues === []) {
                continue;
            }

            $items[] = ['type' => 'heading', 'lines' => [$heading], 'height' => $this->headingHeight($fontSize)];

            foreach ($clues as $clue) {
                $number = $clue['number'].'.';
                $lines = $this->wrap(
                    (string) ($clue['clue'] ?? ''),
                    'normal',
                    $fontSize,
                    $columnWidth,
                    $this->measure($number.' ', 'bold', $fontSize),
                );

                $items[] = [
                    'type' => 'clue',
                    'number' => $number,
                    'lines' => $lines,
                    'height' => count($lines) * $this->lineHeight($fontSize),
                ];
            }
        }

        return $items;
    }

    /**
     * Fill the regions in order, moving to the next when an item does not
     * fit, and continuing on pages of three full-height columns when the
     * grid's page is full. A heading always stays with the clue after it.
     *
     * @param  list<array{type: string, lines: list<string>, number?: string, height: float}>  $items
     * @param  list<array{left: float, top: float, width: float, height: float}>  $gridPageRegions
     * @param  list<array{left: float, top: float, width: float, height: float}>  $fullPageRegions
     * @return list<list<array{left: float, top: float, width: float, items: list<array{type: string, lines: list<string>, number?: string, marginTop?: float}>}>>
     */
    private function distribute(array $items, array $gridPageRegions, array $fullPageRegions, float $fontSize): array
    {
        $pages = [];
        $regions = $gridPageRegions;
        $regionIndex = 0;
        $used = 0.0;
        $placed = array_fill(0, count($regions), []);

        foreach ($items as $index => $item) {
            $isHeading = $item['type'] === 'heading';

            while (true) {
                $marginTop = $isHeading && $used > 0 ? $this->headingSpacing() : 0.0;
                $needed = $marginTop + $item['height'];
                if ($isHeading && isset($items[$index + 1])) {
                    $needed += $items[$index + 1]['height'];
                }

                $isEmptyFullColumn = $used === 0.0 && $pages !== [];
                if ($used + $needed <= $regions[$regionIndex]['height'] || $isEmptyFullColumn) {
                    break;
                }

                $regionIndex++;
                $used = 0.0;
                if ($regionIndex === count($regions)) {
                    $pages[] = $this->placedRegions($regions, $placed);
                    $regions = $fullPageRegions;
                    $regionIndex = 0;
                    $placed = array_fill(0, count($regions), []);
                }
            }

            $used += $marginTop + $item['height'] + ($isHeading ? 0.0 : $this->clueSpacing($fontSize));
            unset($item['height']);
            $placed[$regionIndex][] = $isHeading ? [...$item, 'marginTop' => $marginTop] : $item;
        }

        $pages[] = $this->placedRegions($regions, $placed);

        return $pages;
    }

    /**
     * @param  list<array{left: float, top: float, width: float, height: float}>  $regions
     * @param  list<list<array<string, mixed>>>  $placed
     * @return list<array{left: float, top: float, width: float, items: list<array<string, mixed>>}>
     */
    private function placedRegions(array $regions, array $placed): array
    {
        $result = [];
        foreach ($regions as $index => $region) {
            if ($placed[$index] === []) {
                continue;
            }
            $result[] = [
                'left' => $region['left'],
                'top' => $region['top'],
                'width' => $region['width'],
                'items' => $placed[$index],
            ];
        }

        return $result;
    }

    /**
     * The title block, narrative and image as blocks of known height, so the
     * space left for the grid and clues is known before anything is rendered.
     *
     * @param  array{title: string, author?: ?string, copyright?: ?string, notes?: ?string, narrative?: ?string, imagePath?: ?string}  $header
     * @return list<array{type: string, height: float, lines?: list<string>, lineHeight?: float, imageWidth?: float, imageHeight?: float}>
     */
    private function headerBlocks(array $header, float $pageWidth): array
    {
        $blocks = [];

        $text = function (string $type, ?string $content, string $fontStyle, float $fontSize, float $lineHeight, float $width, float $spaceAfter) use (&$blocks): void {
            if (blank($content)) {
                return;
            }
            $lines = $this->wrap(trim($content), $fontStyle, $fontSize, $width);
            $blocks[] = [
                'type' => $type,
                'lines' => $lines,
                'lineHeight' => $lineHeight,
                'height' => count($lines) * $lineHeight + $spaceAfter,
            ];
        };

        $copyright = $header['copyright'] ?? null;
        if (filled($copyright) && ! preg_match('/^\s*(©|\(c\)|copyright\b)/iu', $copyright)) {
            $copyright = '© '.trim($copyright);
        }

        $text('title', $header['title'], 'bold', 18.0, 22.0, $pageWidth, 2.0);
        $text('meta', filled($header['author'] ?? null) ? 'By '.$header['author'] : null, 'normal', 10.0, 13.0, $pageWidth, 0.0);
        $text('meta', $copyright, 'normal', 10.0, 13.0, $pageWidth, 0.0);
        $text('notes', $header['notes'] ?? null, 'italic', 9.0, 12.0, $pageWidth, 0.0);
        $blocks[] = ['type' => 'space', 'height' => 10.0];
        $text('narrative', $header['narrative'] ?? null, 'normal', 10.0, 14.0, $pageWidth - 16.0, 12.0);

        $imagePath = $header['imagePath'] ?? null;
        if ($imagePath && is_file($imagePath)) {
            $blocks[] = $this->imageBlock($imagePath, $pageWidth);
        }

        return $blocks;
    }

    /**
     * @return array{type: string, height: float, imageWidth: float, imageHeight: float}
     */
    private function imageBlock(string $imagePath, float $pageWidth): array
    {
        $size = @getimagesize($imagePath);
        $naturalWidth = ($size[0] ?? 0) * 0.75;
        $naturalHeight = ($size[1] ?? 0) * 0.75;

        if ($naturalWidth <= 0 || $naturalHeight <= 0) {
            $naturalWidth = $naturalHeight = self::MAX_IMAGE_HEIGHT;
        }

        $scale = min(1.0, $pageWidth / $naturalWidth, self::MAX_IMAGE_HEIGHT / $naturalHeight);

        return [
            'type' => 'image',
            'imageWidth' => round($naturalWidth * $scale, 2),
            'imageHeight' => round($naturalHeight * $scale, 2),
            'height' => round($naturalHeight * $scale, 2) + 12.0,
        ];
    }

    /**
     * Break text into lines no wider than `$width`, keeping the author's own
     * line breaks. The first line is shortened by `$firstLineIndent`.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $fontStyle, float $fontSize, float $width, float $firstLineIndent = 0.0): array
    {
        $lines = [];
        $width -= self::LINE_SAFETY;

        foreach (preg_split('/\R/u', $text) ?: [''] as $paragraph) {
            $line = '';

            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $available = $width - ($lines === [] ? $firstLineIndent : 0.0);
                $candidate = $line === '' ? $word : $line.' '.$word;

                if ($this->measure($candidate, $fontStyle, $fontSize) <= $available) {
                    $line = $candidate;

                    continue;
                }

                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                // A single word wider than the column is broken wherever it runs out of room.
                foreach (mb_str_split($word) as $character) {
                    $available = $width - ($lines === [] ? $firstLineIndent : 0.0);
                    if ($line !== '' && $this->measure($line.$character, $fontStyle, $fontSize) > $available) {
                        $lines[] = $line;
                        $line = '';
                    }
                    $line .= $character;
                }
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function measure(string $text, string $fontStyle, float $fontSize): float
    {
        $this->measureText ??= $this->dompdfMeasurer();

        return ($this->measureText)($text, $fontStyle, $fontSize);
    }

    /**
     * @return Closure(string, string, float): float
     */
    private function dompdfMeasurer(): Closure
    {
        $metrics = app('dompdf')->getFontMetrics();
        $fonts = [];

        return function (string $text, string $fontStyle, float $fontSize) use ($metrics, &$fonts): float {
            $fonts[$fontStyle] ??= $metrics->getFont('helvetica', $fontStyle);

            return $metrics->getTextWidth($text, $fonts[$fontStyle], $fontSize);
        };
    }

    private function lineHeight(float $fontSize): float
    {
        return round($fontSize * 1.25, 2);
    }

    private function clueSpacing(float $fontSize): float
    {
        return round($fontSize * 0.25, 2);
    }

    /** The space above a heading that does not start its column. */
    private function headingSpacing(): float
    {
        return 8.0;
    }

    /** Heading text, its rule and the space below it; mirrors `.clues-heading` in the view. */
    private function headingHeight(float $fontSize): float
    {
        return round(($fontSize + 2) * 1.25, 2) + 3.0 + 5.0;
    }
}
