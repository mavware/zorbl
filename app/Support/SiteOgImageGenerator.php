<?php

namespace App\Support;

use App\Console\Commands\GenerateDefaultOgImage;
use App\Support\Concerns\DrawsOnImage;
use GdImage;
use RuntimeException;

/**
 * Renders the site-wide default 1200x630 Open Graph share image — the fallback
 * used when a page has no puzzle-specific image. Mirrors the brand language of
 * the per-puzzle {@see OgImageGenerator}: dark canvas, amber accent, and a
 * decorative crossword grid on the left with the brand copy on the right.
 *
 * The output is deterministic (depends only on config), so it's generated once
 * into `public/og-default.png` by {@see GenerateDefaultOgImage}.
 */
class SiteOgImageGenerator
{
    use DrawsOnImage;

    private const CANVAS_W = 1200;

    private const CANVAS_H = 630;

    private const GRID_BOX = 500;

    private const GRID_X = 65;

    private const GRID_Y = 65;

    private const TEXT_X = 620;

    private const COLOR_BG = [0x0A, 0x0A, 0x0A];

    private const COLOR_GRID_LINE = [0x52, 0x52, 0x52];

    private const COLOR_BLOCK = [0x1C, 0x1C, 0x1C];

    private const COLOR_CELL = [0xF5, 0xF5, 0xF5];

    private const COLOR_NUMBER = [0x73, 0x73, 0x73];

    private const COLOR_LETTER = [0x0A, 0x0A, 0x0A];

    private const COLOR_AMBER = [0xF5, 0x9E, 0x0B];

    private const COLOR_TITLE = [0xFA, 0xFA, 0xFA];

    private const COLOR_TEXT = [0xA3, 0xA3, 0xA3];

    private const COLOR_MUTED = [0x73, 0x73, 0x73];

    /**
     * A fixed 5x5 mini crossword. `#` = block and a letter = a filled answer
     * cell. It fully interlocks: BUILD / SOLVE run across the top and bottom
     * rows, and BONUS / IDEAL / DELVE run down the three open columns — a
     * compact nod to the product's two halves.
     *
     * @var list<string>
     */
    private const GRID = [
        'BUILD',
        'O#D#E',
        'N#E#L',
        'U#A#V',
        'SOLVE',
    ];

    /**
     * Render the default share image and return the raw PNG bytes.
     */
    public function render(): string
    {
        $canvas = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        if ($canvas === false) {
            throw new RuntimeException('Failed to allocate GD canvas.');
        }

        $this->fill($canvas, 0, 0, self::CANVAS_W, self::CANVAS_H, self::COLOR_BG);

        $this->drawGrid($canvas);
        $this->drawText($canvas);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    private function drawGrid(GdImage $canvas): void
    {
        $bold = $this->fontPath('DejaVuSans-Bold.ttf');

        $rows = count(self::GRID);
        $cols = strlen(self::GRID[0]);
        $cellSize = (int) floor(self::GRID_BOX / max($rows, $cols));
        $originX = self::GRID_X + (int) floor((self::GRID_BOX - $cellSize * $cols) / 2);
        $originY = self::GRID_Y + (int) floor((self::GRID_BOX - $cellSize * $rows) / 2);

        $numberFontSize = max(9.0, $cellSize * 0.24);
        $letterFontSize = max(16.0, $cellSize * 0.5);
        $number = 0;

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $char = self::GRID[$row][$col];
                $x = $originX + $col * $cellSize;
                $y = $originY + $row * $cellSize;

                if ($char === '#') {
                    $this->fill($canvas, $x, $y, $x + $cellSize, $y + $cellSize, self::COLOR_BLOCK);
                    $this->cellBorder($canvas, $x, $y, $cellSize);

                    continue;
                }

                $this->fill($canvas, $x, $y, $x + $cellSize, $y + $cellSize, self::COLOR_CELL);
                $this->cellBorder($canvas, $x, $y, $cellSize);

                // Clue number in the corner of any cell that starts a word.
                if ($this->startsWord(self::GRID, $row, $col, $rows, $cols)) {
                    $number++;
                    $this->ttfText(
                        $canvas,
                        (string) $number,
                        $x + 4,
                        $y + (int) ceil($numberFontSize) + 2,
                        $numberFontSize,
                        $bold,
                        self::COLOR_NUMBER,
                    );
                }

                if ($char !== ' ') {
                    $letterWidth = $this->ttfWidth($char, $letterFontSize, $bold);
                    $this->ttfText(
                        $canvas,
                        $char,
                        $x + (int) round(($cellSize - $letterWidth) / 2),
                        $y + $cellSize - (int) round($cellSize * 0.28),
                        $letterFontSize,
                        $bold,
                        self::COLOR_LETTER,
                    );
                }
            }
        }
    }

    private function drawText(GdImage $canvas): void
    {
        $bold = $this->fontPath('DejaVuSans-Bold.ttf');
        $regular = $this->fontPath('DejaVuSans.ttf');

        $appName = (string) config('app.name');

        $this->ttfText($canvas, mb_strtoupper(__('Crossword Puzzles')), self::TEXT_X, 150, 16, $bold, self::COLOR_AMBER, letterSpacing: 4);

        $this->wrapTtfText($canvas, $appName, self::TEXT_X, 215, 62, $bold, self::COLOR_TITLE, maxWidth: 540, lineHeight: 72, maxLines: 2);

        $this->wrapTtfText(
            $canvas,
            __('Build crosswords with a visual editor and solve puzzles from constructors worldwide.'),
            self::TEXT_X,
            self::GRID_Y + 300,
            23,
            $regular,
            self::COLOR_TEXT,
            maxWidth: 520,
            lineHeight: 36,
            maxLines: 3,
        );

        // No domain/URL here on purpose: this static image is committed and
        // served across environments, so baking in a host would be wrong
        // wherever APP_URL differs. The large brand name carries recognition.
        $this->ttfText($canvas, __('Build & solve · Free forever'), self::TEXT_X, self::CANVAS_H - 60, 24, $bold, self::COLOR_AMBER);
    }

    /**
     * A cell starts a word if it has no fillable neighbour above (down word) or
     * to the left (across word) — the standard crossword numbering rule.
     *
     * @param  list<string>  $grid
     */
    private function startsWord(array $grid, int $row, int $col, int $rows, int $cols): bool
    {
        $isFill = fn (int $r, int $c): bool => $r >= 0 && $r < $rows && $c >= 0 && $c < $cols && $grid[$r][$c] !== '#';

        $startsAcross = ! $isFill($row, $col - 1) && $isFill($row, $col + 1);
        $startsDown = ! $isFill($row - 1, $col) && $isFill($row + 1, $col);

        return $startsAcross || $startsDown;
    }

    private function cellBorder(GdImage $canvas, int $x, int $y, int $size): void
    {
        imagerectangle($canvas, $x, $y, $x + $size, $y + $size, $this->color($canvas, self::COLOR_GRID_LINE));
    }
}
