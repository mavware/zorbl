<?php

namespace App\Services;

use App\Models\Crossword;
use DOMDocument;
use DOMElement;

class JpzExporter
{
    private const NS = 'http://crossword.info/xml/rectangular-puzzle';

    public function __construct(private GridNumberer $numberer) {}

    /**
     * Export a Crossword model to gzip-compressed .jpz format.
     */
    public function export(Crossword $crossword): string
    {
        return gzencode($this->toXml($crossword));
    }

    /**
     * Export a Crossword model to uncompressed XML string.
     */
    public function toXml(Crossword $crossword): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::NS, 'rectangular-puzzle');
        $dom->appendChild($root);

        $this->buildMetadata($dom, $root, $crossword);
        $this->buildCrossword($dom, $root, $crossword);

        return $dom->saveXML();
    }

    private function buildMetadata(DOMDocument $dom, DOMElement $root, Crossword $crossword): void
    {
        $hasMetadata = $crossword->title || $crossword->author || $crossword->copyright || $crossword->notes;

        if (! $hasMetadata) {
            return;
        }

        $metadata = $dom->createElementNS(self::NS, 'metadata');
        $root->appendChild($metadata);

        if ($crossword->title) {
            $metadata->appendChild($dom->createElementNS(self::NS, 'title', htmlspecialchars($crossword->title)));
        }

        if ($crossword->author) {
            $metadata->appendChild($dom->createElementNS(self::NS, 'creator', htmlspecialchars($crossword->author)));
        }

        if ($crossword->copyright) {
            $metadata->appendChild($dom->createElementNS(self::NS, 'copyright', htmlspecialchars($crossword->copyright)));
        }

        if ($crossword->notes) {
            $metadata->appendChild($dom->createElementNS(self::NS, 'description', htmlspecialchars($crossword->notes)));
        }
    }

    private function buildCrossword(DOMDocument $dom, DOMElement $root, Crossword $crossword): void
    {
        $crosswordEl = $dom->createElementNS(self::NS, 'crossword');
        $root->appendChild($crosswordEl);

        $this->buildGrid($dom, $crosswordEl, $crossword);

        $result = $this->numberer->number($crossword->grid, $crossword->width, $crossword->height);

        $this->buildWords($dom, $crosswordEl, $result, $crossword);
        $this->buildClues($dom, $crosswordEl, $result, $crossword);
    }

    private function buildGrid(DOMDocument $dom, DOMElement $crosswordEl, Crossword $crossword): void
    {
        $gridEl = $dom->createElementNS(self::NS, 'grid');
        $gridEl->setAttribute('width', (string) $crossword->width);
        $gridEl->setAttribute('height', (string) $crossword->height);
        $crosswordEl->appendChild($gridEl);

        $gridLook = $dom->createElementNS(self::NS, 'grid-look');
        $gridLook->setAttribute('numbering-scheme', 'normal');
        $gridLook->setAttribute('cell-size-in-pixels', '21');
        $gridEl->appendChild($gridLook);

        $styles = $crossword->styles ?? [];

        for ($row = 0; $row < $crossword->height; $row++) {
            for ($col = 0; $col < $crossword->width; $col++) {
                $cellEl = $dom->createElementNS(self::NS, 'cell');
                $cellEl->setAttribute('x', (string) ($col + 1));
                $cellEl->setAttribute('y', (string) ($row + 1));

                $gridValue = $crossword->grid[$row][$col];
                $solutionValue = $crossword->solution[$row][$col] ?? '';

                if ($gridValue === '#') {
                    $cellEl->setAttribute('type', 'block');
                } elseif ($gridValue === null) {
                    $cellEl->setAttribute('type', 'void');
                } else {
                    if ($solutionValue !== '' && $solutionValue !== '#' && $solutionValue !== null) {
                        $cellEl->setAttribute('solution', strtoupper($solutionValue));
                    }

                    if (is_int($gridValue) && $gridValue > 0) {
                        $cellEl->setAttribute('number', (string) $gridValue);
                    }

                    $styleKey = "{$row},{$col}";
                    if (isset($styles[$styleKey]['shapebg']) && $styles[$styleKey]['shapebg'] === 'circle') {
                        $cellEl->setAttribute('background-shape', 'circle');
                    }
                }

                $gridEl->appendChild($cellEl);
            }
        }
    }

    private function buildWords(DOMDocument $dom, DOMElement $crosswordEl, array $result, Crossword $crossword): void
    {
        $wordId = 1;
        $this->wordIdMap = [];

        foreach (['across', 'down'] as $direction) {
            foreach ($result[$direction] as $slot) {
                $wordEl = $dom->createElementNS(self::NS, 'word');
                $wordEl->setAttribute('id', (string) $wordId);

                $cellsEl = $dom->createElementNS(self::NS, 'cells');

                if ($direction === 'across') {
                    $startCol = $slot['col'] + 1;
                    $endCol = $slot['col'] + $slot['length'];
                    $cellsEl->setAttribute('x', $startCol === $endCol ? (string) $startCol : "{$startCol}-{$endCol}");
                    $cellsEl->setAttribute('y', (string) ($slot['row'] + 1));
                } else {
                    $startRow = $slot['row'] + 1;
                    $endRow = $slot['row'] + $slot['length'];
                    $cellsEl->setAttribute('x', (string) ($slot['col'] + 1));
                    $cellsEl->setAttribute('y', $startRow === $endRow ? (string) $startRow : "{$startRow}-{$endRow}");
                }

                $wordEl->appendChild($cellsEl);
                $crosswordEl->appendChild($wordEl);

                $this->wordIdMap[$direction][$slot['number']] = $wordId;
                $wordId++;
            }
        }
    }

    private function buildClues(DOMDocument $dom, DOMElement $crosswordEl, array $result, Crossword $crossword): void
    {
        $directions = [
            'across' => ['title' => 'Across', 'clues' => $crossword->clues_across],
            'down' => ['title' => 'Down', 'clues' => $crossword->clues_down],
        ];

        foreach ($directions as $direction => $config) {
            $cluesEl = $dom->createElementNS(self::NS, 'clues');
            $crosswordEl->appendChild($cluesEl);

            $titleEl = $dom->createElementNS(self::NS, 'title');
            $b = $dom->createElementNS(self::NS, 'b', $config['title']);
            $titleEl->appendChild($b);
            $cluesEl->appendChild($titleEl);

            $clueMap = collect($config['clues'])->keyBy('number');

            foreach ($result[$direction] as $slot) {
                $clueData = $clueMap->get($slot['number']);
                $clueText = $clueData['clue'] ?? '';
                $wordId = $this->wordIdMap[$direction][$slot['number']] ?? 0;

                $clueEl = $dom->createElementNS(self::NS, 'clue');
                $clueEl->setAttribute('word', (string) $wordId);
                $clueEl->setAttribute('number', (string) $slot['number']);
                $clueEl->appendChild($dom->createTextNode($clueText));
                $cluesEl->appendChild($clueEl);
            }
        }
    }

    /** @var array<string, array<int, int>> */
    private array $wordIdMap = [];
}
