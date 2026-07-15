<?php

namespace App\Support;

use App\Models\Crossword;
use GdImage;
use Illuminate\Support\Str;
use RuntimeException;
use CrosswordBuilder\CrosswordIO\GridNumberer;

class OgImageGenerator
{
    private const CANVAS_W = 1200;

    private const CANVAS_H = 630;

    private const GRID_BOX = 500;

    private const GRID_X = 65;

    private const GRID_Y = 65;

    private const TEXT_X = 620;

    private const COLOR_BG = [0x0A, 0x0A, 0x0A];

    private const COLOR_GRID_LINE = [0x52, 0x52, 0x52];

    private const COLOR_BLOCK = [0x52, 0x52, 0x52];

    private const COLOR_CELL = [0xF5, 0xF5, 0xF5];

    private const COLOR_NUMBER = [0x52, 0x52, 0x52];

    private const COLOR_AMBER = [0xF5, 0x9E, 0x0B];

    private const COLOR_TITLE = [0xFA, 0xFA, 0xFA];

    private const COLOR_TEXT = [0xA3, 0xA3, 0xA3];

    private const COLOR_MUTED = [0x73, 0x73, 0x73];

    private const COLOR_BAR = [0xF5, 0x9E, 0x0B];

    public function __construct(private GridNumberer $numberer) {}

    /**
     * Render a 1200x630 PNG for sharing this puzzle on social platforms.
     * Returns the raw PNG bytes.
     */
    public function render(Crossword $crossword): string
    {
        $canvas = imagecreatetruecolor(self::CANVAS_W, self::CANVAS_H);
        if ($canvas === false) {
            throw new RuntimeException('Failed to allocate GD canvas.');
        }

        $this->fill($canvas, 0, 0, self::CANVAS_W, self::CANVAS_H, self::COLOR_BG);

        $this->drawGrid($canvas, $crossword);
        $this->drawText($canvas, $crossword);
        $this->drawBrand($canvas);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    private function drawGrid(GdImage $canvas, Crossword $crossword): void
    {
        $grid = $crossword->grid ?? [];
        $width = $crossword->width;
        $height = $crossword->height;
        $styles = is_array($crossword->styles) ? $crossword->styles : [];

        if ($width <= 0 || $height <= 0 || $grid === []) {
            return;
        }

        $numbered = $this->numberer->number($grid, $width, $height, $styles)['grid'];

        $cellSize = (int) floor(self::GRID_BOX / max($width, $height));
        $gridPxW = $cellSize * $width;
        $gridPxH = $cellSize * $height;
        $originX = self::GRID_X + (int) floor((self::GRID_BOX - $gridPxW) / 2);
        $originY = self::GRID_Y + (int) floor((self::GRID_BOX - $gridPxH) / 2);

        $regular = $this->fontPath('DejaVuSans.ttf');
        $bold = $this->fontPath('DejaVuSans-Bold.ttf');
        $numberFontSize = max(7.0, $cellSize * 0.28);

        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                $cell = $numbered[$row][$col] ?? null;
                $x = $originX + $col * $cellSize;
                $y = $originY + $row * $cellSize;

                if ($cell === null) {
                    continue; // void
                }

                if ($cell === '#') {
                    $this->fill($canvas, $x, $y, $x + $cellSize, $y + $cellSize, self::COLOR_BLOCK);
                    $this->cellBorder($canvas, $x, $y, $cellSize);

                    continue;
                }

                $this->fill($canvas, $x, $y, $x + $cellSize, $y + $cellSize, self::COLOR_CELL);
                $this->cellBorder($canvas, $x, $y, $cellSize);

                if (is_int($cell) && $cell > 0) {
                    $this->ttfText(
                        $canvas,
                        (string) $cell,
                        $x + 3,
                        $y + (int) ceil($numberFontSize) + 1,
                        $numberFontSize,
                        $bold,
                        self::COLOR_NUMBER,
                    );
                }

                $bars = $styles["{$row},{$col}"]['bars'] ?? null;
                if (is_array($bars)) {
                    $this->drawBars($canvas, $x, $y, $cellSize, $bars);
                }
            }
        }

        unset($regular);
    }

    /**
     * @param  array<int, string>  $bars
     */
    private function drawBars(GdImage $canvas, int $x, int $y, int $size, array $bars): void
    {
        $color = $this->color($canvas, self::COLOR_BAR);
        $thickness = max(2, (int) round($size * 0.08));

        if (in_array('top', $bars, true)) {
            imagefilledrectangle($canvas, $x, $y, $x + $size, $y + $thickness, $color);
        }
        if (in_array('bottom', $bars, true)) {
            imagefilledrectangle($canvas, $x, $y + $size - $thickness, $x + $size, $y + $size, $color);
        }
        if (in_array('left', $bars, true)) {
            imagefilledrectangle($canvas, $x, $y, $x + $thickness, $y + $size, $color);
        }
        if (in_array('right', $bars, true)) {
            imagefilledrectangle($canvas, $x + $size - $thickness, $y, $x + $size, $y + $size, $color);
        }
    }

    private function drawText(GdImage $canvas, Crossword $crossword): void
    {
        $bold = $this->fontPath('DejaVuSans-Bold.ttf');
        $regular = $this->fontPath('DejaVuSans.ttf');

        $title = $crossword->displayTitle();
        $title = $this->truncate($title, 48);

        $author = $crossword->user?->copyright_name ?: $crossword->user?->name ?? __('Anonymous');
        $stats = sprintf('%d × %d', $crossword->width, $crossword->height);
        if ($crossword->difficulty_label !== null && $crossword->difficulty_label !== '') {
            $stats .= ' · '.Str::title((string) $crossword->difficulty_label);
        }

        $this->ttfText($canvas, __('CROSSWORD'), self::TEXT_X, 130, 16, $bold, self::COLOR_AMBER, letterSpacing: 4);
        $this->wrapTtfText($canvas, $title, self::TEXT_X, 175, 50, $bold, self::COLOR_TITLE, maxWidth: 540, lineHeight: 60, maxLines: 2);

        $this->ttfText($canvas, __('by :name', ['name' => $this->truncate($author, 40)]), self::TEXT_X, 360, 22, $regular, self::COLOR_TEXT);
        $this->ttfText($canvas, $stats, self::TEXT_X, 400, 20, $regular, self::COLOR_MUTED);
    }

    private function drawBrand(GdImage $canvas): void
    {
        $bold = $this->fontPath('DejaVuSans-Bold.ttf');
        $regular = $this->fontPath('DejaVuSans.ttf');

        // Bottom call-to-action.
        $this->ttfText(
            $canvas,
            __('Solve at :app', ['app' => config('app.name')]),
            self::TEXT_X,
            self::CANVAS_H - 75,
            26,
            $bold,
            self::COLOR_AMBER,
        );

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url');
        $this->ttfText(
            $canvas,
            (string) $host,
            self::TEXT_X,
            self::CANVAS_H - 45,
            18,
            $regular,
            self::COLOR_MUTED,
        );
    }

    private function cellBorder(GdImage $canvas, int $x, int $y, int $size): void
    {
        $color = $this->color($canvas, self::COLOR_GRID_LINE);
        imagerectangle($canvas, $x, $y, $x + $size, $y + $size, $color);
    }

    /**
     * @param  array<int, int>  $rgb
     */
    private function fill(GdImage $canvas, int $x1, int $y1, int $x2, int $y2, array $rgb): void
    {
        imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $this->color($canvas, $rgb));
    }

    /**
     * @param  array<int, int>  $rgb
     */
    private function color(GdImage $canvas, array $rgb): int
    {
        $color = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);

        return $color === false ? 0 : $color;
    }

    /**
     * @param  array<int, int>  $rgb
     */
    private function ttfText(GdImage $canvas, string $text, int $x, int $y, float $size, string $font, array $rgb, int $letterSpacing = 0): void
    {
        if ($letterSpacing === 0) {
            imagettftext($canvas, $size, 0, $x, $y, $this->color($canvas, $rgb), $font, $text);

            return;
        }

        $cursor = $x;
        foreach (mb_str_split($text) as $char) {
            imagettftext($canvas, $size, 0, $cursor, $y, $this->color($canvas, $rgb), $font, $char);
            $bbox = imagettfbbox($size, 0, $font, $char);
            $cursor += ($bbox[2] - $bbox[0]) + $letterSpacing;
        }
    }

    /**
     * Word-wrap TTF text within $maxWidth, drawing up to $maxLines lines.
     *
     * @param  array<int, int>  $rgb
     */
    private function wrapTtfText(GdImage $canvas, string $text, int $x, int $y, float $size, string $font, array $rgb, int $maxWidth, int $lineHeight, int $maxLines): void
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';
        $overflowed = false;

        foreach ($words as $i => $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            $bbox = imagettfbbox($size, 0, $font, $candidate);
            $w = $bbox[2] - $bbox[0];

            if ($w <= $maxWidth) {
                $line = $candidate;

                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }

            if (count($lines) >= $maxLines) {
                $line = '';
                $overflowed = $i < count($words);
                break;
            }

            $line = $word;
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        if ($overflowed && $lines !== []) {
            $last = $lines[count($lines) - 1];
            while ($last !== '') {
                $bbox = imagettfbbox($size, 0, $font, $last.'…');
                if (($bbox[2] - $bbox[0]) <= $maxWidth) {
                    $lines[count($lines) - 1] = $last.'…';
                    break;
                }
                $last = rtrim(mb_substr($last, 0, -1));
            }
        }

        $cursorY = $y;
        foreach ($lines as $current) {
            $this->ttfText($canvas, $current, $x, $cursorY, $size, $font, $rgb);
            $cursorY += $lineHeight;
        }
    }

    private function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }

    private function fontPath(string $file): string
    {
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/'.$file);
        if (! is_file($path)) {
            throw new RuntimeException("OG image font missing: {$path}");
        }

        return $path;
    }
}
