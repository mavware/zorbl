<?php

namespace App\Support\Concerns;

use GdImage;
use RuntimeException;

/**
 * Shared GD primitives for rendering brand imagery (OG share images, etc.).
 * Colours are passed as `[r, g, b]` int triples.
 */
trait DrawsOnImage
{
    /**
     * @param  array<int, int>  $rgb
     */
    protected function fill(GdImage $canvas, int $x1, int $y1, int $x2, int $y2, array $rgb): void
    {
        imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $this->color($canvas, $rgb));
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function color(GdImage $canvas, array $rgb): int
    {
        $color = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);

        return $color === false ? 0 : $color;
    }

    /**
     * Draw a single line of TrueType text, optionally with letter spacing.
     *
     * @param  array<int, int>  $rgb
     */
    protected function ttfText(GdImage $canvas, string $text, int $x, int $y, float $size, string $font, array $rgb, int $letterSpacing = 0): void
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
     * Pixel width of a rendered TrueType string.
     */
    protected function ttfWidth(string $text, float $size, string $font): int
    {
        $bbox = imagettfbbox($size, 0, $font, $text);

        return $bbox[2] - $bbox[0];
    }

    /**
     * Word-wrap TTF text within $maxWidth, drawing up to $maxLines lines.
     *
     * @param  array<int, int>  $rgb
     */
    protected function wrapTtfText(GdImage $canvas, string $text, int $x, int $y, float $size, string $font, array $rgb, int $maxWidth, int $lineHeight, int $maxLines): void
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';
        $overflowed = false;

        foreach ($words as $i => $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;

            if ($this->ttfWidth($candidate, $size, $font) <= $maxWidth) {
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
                if ($this->ttfWidth($last.'…', $size, $font) <= $maxWidth) {
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

    protected function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 1).'…' : $text;
    }

    protected function fontPath(string $file): string
    {
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/'.$file);
        if (! is_file($path)) {
            throw new RuntimeException("OG image font missing: {$path}");
        }

        return $path;
    }
}
