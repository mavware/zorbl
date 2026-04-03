<?php

namespace App\Services;

use App\Exceptions\JpzImportException;
use SimpleXMLElement;

class JpzImporter
{
    private const string NS = 'https://crossword.info/xml/rectangular-puzzle';

    public function __construct(private readonly GridNumberer $numberer) {}

    /**
     * Parse a .jpz file's contents into an array suitable for Crossword::create().
     *
     * @return array<string, mixed>
     *
     * @throws JpzImportException
     */
    public function import(string $contents): array
    {
        $xml = $this->decompress($contents);
        $doc = $this->parseXml($xml);

        $crossword = $this->findCrosswordElement($doc);

        $gridEl = $crossword->children(self::NS)->grid ?? $crossword->grid ?? null;
        if ($gridEl === null) {
            throw new JpzImportException('Missing grid element');
        }

        $width = (int) $this->attr($gridEl, 'width');
        $height = (int) $this->attr($gridEl, 'height');

        if ($width < 1 || $height < 1) {
            throw new JpzImportException('Invalid grid dimensions');
        }

        [$grid, $solution, $styles] = $this->parseGrid($gridEl, $width, $height);

        $wordMap = $this->parseWords($crossword);
        [$cluesAcross, $cluesDown] = $this->parseClues($crossword, $wordMap);

        $result = $this->numberer->number($grid, $width, $height, $styles ?? []);
        $finalAcross = $this->matchCluesToSlots($cluesAcross, $result['across']);
        $finalDown = $this->matchCluesToSlots($cluesDown, $result['down']);

        $metadata = $this->parseMetadata($doc);

        return [
            'title' => $metadata['title'],
            'author' => $metadata['author'],
            'copyright' => $metadata['copyright'],
            'notes' => $metadata['notes'],
            'width' => $width,
            'height' => $height,
            'kind' => 'https://ipuz.org/crossword#1',
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $finalAcross,
            'clues_down' => $finalDown,
            'styles' => $styles ?: null,
            'metadata' => null,
        ];
    }

    /**
     * Try to decompress gzip content, fall back to raw string.
     */
    private function decompress(string $contents): string
    {
        // Check for gzip magic bytes (1f 8b) before attempting decompression
        if (strlen($contents) >= 2 && $contents[0] === "\x1f" && $contents[1] === "\x8b") {
            $decoded = gzdecode($contents);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $contents;
    }

    /**
     * Parse XML string, handling both namespaced and non-namespaced documents.
     *
     * @throws JpzImportException
     */
    private function parseXml(string $xml): SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $message = ! empty($errors) ? $errors[0]->message : 'Unknown XML error';

            throw new JpzImportException('Invalid XML: '.trim($message));
        }

        return $doc;
    }

    /**
     * Find the crossword element in the document.
     *
     * @throws JpzImportException
     */
    private function findCrosswordElement(SimpleXMLElement $doc): SimpleXMLElement
    {
        // Try namespaced first
        $ns = $doc->children(self::NS);
        if (isset($ns->crossword)) {
            return $ns->crossword;
        }

        // Try non-namespaced
        if (isset($doc->crossword)) {
            return $doc->crossword;
        }

        throw new JpzImportException('Missing crossword element');
    }

    /**
     * Parse metadata from the document.
     *
     * @return array{title: ?string, author: ?string, copyright: ?string, notes: ?string}
     */
    private function parseMetadata(SimpleXMLElement $doc): array
    {
        $meta = $doc->children(self::NS)->metadata ?? $doc->metadata ?? null;

        $title = null;
        $author = null;
        $copyright = null;
        $notes = null;

        if ($meta !== null) {
            $nsMeta = $meta->children(self::NS);
            $title = $this->getTextContent($nsMeta->title ?? $meta->title ?? null);
            $author = $this->getTextContent($nsMeta->creator ?? $meta->creator ?? null);
            $copyright = $this->getTextContent($nsMeta->copyright ?? $meta->copyright ?? null);
            $notes = $this->getTextContent($nsMeta->description ?? $meta->description ?? null);
        }

        return compact('title', 'author', 'copyright', 'notes');
    }

    /**
     * Parse grid cells into grid, solution, and styles arrays.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function parseGrid(SimpleXMLElement $gridEl, int $width, int $height): array
    {
        $grid = array_fill(0, $height, array_fill(0, $width, 0));
        $solution = array_fill(0, $height, array_fill(0, $width, ''));
        $styles = [];

        $cells = $gridEl->children(self::NS)->cell ?? $gridEl->cell ?? [];

        foreach ($cells as $cell) {
            $x = (int) $this->attr($cell, 'x');
            $y = (int) $this->attr($cell, 'y');
            $col = $x - 1;
            $row = $y - 1;

            if ($row < 0 || $row >= $height || $col < 0 || $col >= $width) {
                continue;
            }

            $type = $this->attr($cell, 'type') ?: 'letter';

            if ($type === 'block') {
                $grid[$row][$col] = '#';
                $solution[$row][$col] = '#';
            } elseif ($type === 'void') {
                $grid[$row][$col] = null;
                $solution[$row][$col] = null;
            } else {
                $sol = $this->attr($cell, 'solution');
                $solution[$row][$col] = strtoupper($sol);

                $number = $this->attr($cell, 'number');
                $grid[$row][$col] = $number !== '' ? (int) $number : 0;

                $bgShape = $this->attr($cell, 'background-shape');
                if ($bgShape === 'circle') {
                    $styles["$row,$col"] = ['shapebg' => 'circle'];
                }
            }
        }

        return [$grid, $solution, $styles];
    }

    /**
     * Parse word elements into a map of word ID → cell positions and direction.
     *
     * @return array<int, array{cells: array<int, array{row: int, col: int}>, direction: string}>
     */
    private function parseWords(SimpleXMLElement $crossword): array
    {
        $wordMap = [];
        $words = $crossword->children(self::NS)->word ?? $crossword->word ?? [];

        foreach ($words as $word) {
            $id = (int) $this->attr($word, 'id');
            if ($id === 0) {
                continue;
            }

            // Try explicit x/y attributes on the word element first
            $xAttr = $this->attr($word, 'x');
            $yAttr = $this->attr($word, 'y');

            $cells = [];

            if ($xAttr !== '' && $yAttr !== '') {
                $cells = $this->expandRangePair($xAttr, $yAttr);
            } else {
                // Parse child <cells> elements
                $cellEls = $word->children(self::NS)->cells ?? $word->cells ?? [];
                foreach ($cellEls as $cellEl) {
                    $cx = $this->attr($cellEl, 'x');
                    $cy = $this->attr($cellEl, 'y');
                    $expanded = $this->expandRangePair($cx, $cy);
                    $cells = array_merge($cells, $expanded);
                }
            }

            // Determine direction from cell positions
            $direction = 'across';
            if (count($cells) >= 2 && $cells[0]['col'] === $cells[1]['col']) {
                $direction = 'down';
            }

            $wordMap[$id] = ['cells' => $cells, 'direction' => $direction];
        }

        return $wordMap;
    }

    /**
     * Expand x/y range pairs (e.g., x="1-5", y="3") into cell positions.
     *
     * @return array<int, array{row: int, col: int}>
     */
    private function expandRangePair(string $xRange, string $yRange): array
    {
        $xValues = $this->expandRange($xRange);
        $yValues = $this->expandRange($yRange);

        $cells = [];

        if (count($xValues) > 1 && count($yValues) === 1) {
            // Horizontal word
            foreach ($xValues as $x) {
                $cells[] = ['row' => $yValues[0] - 1, 'col' => $x - 1];
            }
        } elseif (count($yValues) > 1 && count($xValues) === 1) {
            // Vertical word
            foreach ($yValues as $y) {
                $cells[] = ['row' => $y - 1, 'col' => $xValues[0] - 1];
            }
        } else {
            // Single cell or diagonal (handle as individual cells)
            foreach ($yValues as $y) {
                foreach ($xValues as $x) {
                    $cells[] = ['row' => $y - 1, 'col' => $x - 1];
                }
            }
        }

        return $cells;
    }

    /**
     * Expand a range string like "1-5" or "3" into an array of integers.
     *
     * @return array<int, int>
     */
    private function expandRange(string $range): array
    {
        if (str_contains($range, '-')) {
            [$start, $end] = explode('-', $range, 2);

            return range((int) $start, (int) $end);
        }

        return [(int) $range];
    }

    /**
     * Parse clues from the two clue sections (Across and Down).
     *
     * @return array{0: array, 1: array}
     */
    private function parseClues(SimpleXMLElement $crossword, array $wordMap): array
    {
        $across = [];
        $down = [];

        $cluesSections = $crossword->children(self::NS)->clues ?? $crossword->clues ?? [];

        foreach ($cluesSections as $section) {
            $titleEl = $section->children(self::NS)->title ?? $section->title ?? null;
            $titleText = $titleEl !== null ? strip_tags($titleEl->asXML()) : '';

            $isAcross = str_contains(strtolower($titleText), 'across');
            $isDown = str_contains(strtolower($titleText), 'down');

            // If we can't determine from the title, try word map directions
            if (! $isAcross && ! $isDown) {
                // Check the direction of the first clue's word
                $firstClue = ($section->children(self::NS)->clue ?? $section->clue ?? [])[0] ?? null;
                if ($firstClue !== null) {
                    $wordId = (int) $this->attr($firstClue, 'word');
                    if (isset($wordMap[$wordId])) {
                        $isAcross = $wordMap[$wordId]['direction'] === 'across';
                        $isDown = $wordMap[$wordId]['direction'] === 'down';
                    }
                }
            }

            $clueEls = $section->children(self::NS)->clue ?? $section->clue ?? [];

            foreach ($clueEls as $clueEl) {
                $number = (int) $this->attr($clueEl, 'number');
                $clueText = strip_tags($clueEl->asXML());
                $clueText = trim($clueText);

                $entry = ['number' => $number, 'clue' => $clueText];

                if ($isAcross) {
                    $across[] = $entry;
                } elseif ($isDown) {
                    $down[] = $entry;
                }
            }
        }

        return [$across, $down];
    }

    /**
     * Match parsed clues to numbered slots from GridNumberer.
     *
     * @return array<int, array{number: int, clue: string}>
     */
    private function matchCluesToSlots(array $clues, array $slots): array
    {
        $numberMap = collect($clues)->keyBy('number');

        $final = [];
        foreach ($slots as $slot) {
            $clue = $numberMap->get($slot['number']);
            $final[] = [
                'number' => $slot['number'],
                'clue' => $clue['clue'] ?? '',
            ];
        }

        return $final;
    }

    /**
     * Get text content from a SimpleXMLElement, returning null if empty.
     */
    private function getTextContent(?SimpleXMLElement $el): ?string
    {
        if ($el === null) {
            return null;
        }

        $text = trim((string) $el);

        return $text !== '' ? $text : null;
    }

    /**
     * Get an attribute value from a SimpleXMLElement.
     * Uses ->attributes() to work correctly with namespaced elements.
     */
    private function attr(SimpleXMLElement $el, string $name): string
    {
        return (string) ($el->attributes()[$name] ?? '');
    }
}
