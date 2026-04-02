<?php

namespace App\Services;

use App\Exceptions\IpuzImportException;

class IpuzImporter
{
    public function __construct(private GridNumberer $numberer) {}

    /**
     * Parse an .ipuz file's contents into an array suitable for Crossword::create().
     *
     * @return array<string, mixed>
     *
     * @throws IpuzImportException
     */
    public function import(string $contents): array
    {
        $contents = $this->stripCallback($contents);

        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new IpuzImportException('Invalid JSON: '.json_last_error_msg());
        }

        $this->validate($data);

        $width = $data['dimensions']['width'];
        $height = $data['dimensions']['height'];

        $grid = $this->parseGrid($data['puzzle'], $width, $height);
        $styles = $this->extractStyles($data['puzzle'], $width, $height);
        $solution = $this->parseSolution($data['solution'] ?? null, $grid, $width, $height);
        [$cluesAcross, $cluesDown] = $this->parseClues($data['clues'] ?? []);

        $result = $this->renumber($grid, $width, $height, $cluesAcross, $cluesDown);

        $metadata = $this->extractMetadata($data);

        return [
            'title' => $data['title'] ?? null,
            'author' => $data['author'] ?? null,
            'copyright' => $data['copyright'] ?? null,
            'notes' => $data['notes'] ?? null,
            'width' => $width,
            'height' => $height,
            'kind' => $data['kind'][0] ?? 'http://ipuz.org/crossword#1',
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $result['cluesAcross'],
            'clues_down' => $result['cluesDown'],
            'styles' => $styles ?: null,
            'metadata' => $metadata ?: null,
        ];
    }

    private function stripCallback(string $contents): string
    {
        $contents = trim($contents);

        if (str_starts_with($contents, 'ipuz(') && str_ends_with($contents, ')')) {
            $contents = substr($contents, 5, -1);
        }

        return $contents;
    }

    /**
     * @throws IpuzImportException
     */
    private function validate(array $data): void
    {
        if (! isset($data['version'])) {
            throw new IpuzImportException('Missing required field: version');
        }

        if (! isset($data['kind']) || ! is_array($data['kind'])) {
            throw new IpuzImportException('Missing required field: kind');
        }

        if (! isset($data['dimensions']['width'], $data['dimensions']['height'])) {
            throw new IpuzImportException('Missing required field: dimensions (width and height)');
        }

        if (! isset($data['puzzle']) || ! is_array($data['puzzle'])) {
            throw new IpuzImportException('Missing required field: puzzle');
        }

        $width = $data['dimensions']['width'];
        $height = $data['dimensions']['height'];

        if (count($data['puzzle']) !== $height) {
            throw new IpuzImportException('Puzzle grid has '.count($data['puzzle'])." rows, expected {$height}");
        }

        foreach ($data['puzzle'] as $i => $row) {
            if (count($row) !== $width) {
                throw new IpuzImportException("Puzzle row {$i} has ".count($row)." columns, expected {$width}");
            }
        }
    }

    /**
     * Parse the puzzle grid, normalizing all cell formats to plain values.
     *
     * @return array<int, array<int, mixed>>
     */
    private function parseGrid(array $puzzle, int $width, int $height): array
    {
        $grid = [];

        for ($row = 0; $row < $height; $row++) {
            $gridRow = [];

            for ($col = 0; $col < $width; $col++) {
                $cell = $puzzle[$row][$col];
                $gridRow[] = $this->normalizeCellValue($cell);
            }

            $grid[] = $gridRow;
        }

        return $grid;
    }

    private function normalizeCellValue(mixed $cell): mixed
    {
        if ($cell === null || $cell === '#') {
            return $cell;
        }

        if (is_array($cell)) {
            $cellValue = $cell['cell'] ?? 0;

            return $cellValue === '#' ? '#' : $cellValue;
        }

        return $cell;
    }

    /**
     * Extract cell styles (circles, shading, etc.) into a sparse map.
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractStyles(array $puzzle, int $width, int $height): array
    {
        $styles = [];

        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                $cell = $puzzle[$row][$col];

                if (is_array($cell) && isset($cell['style'])) {
                    $styles["{$row},{$col}"] = $cell['style'];
                }
            }
        }

        return $styles;
    }

    /**
     * Parse the solution grid.
     *
     * @return array<int, array<int, string>>
     */
    private function parseSolution(?array $solution, array $grid, int $width, int $height): array
    {
        $result = [];

        for ($row = 0; $row < $height; $row++) {
            $resultRow = [];

            for ($col = 0; $col < $width; $col++) {
                if ($grid[$row][$col] === '#') {
                    $resultRow[] = '#';
                } elseif ($grid[$row][$col] === null) {
                    $resultRow[] = null;
                } elseif ($solution !== null && isset($solution[$row][$col])) {
                    $val = $solution[$row][$col];
                    $resultRow[] = is_array($val) ? ($val['value'] ?? '') : (string) ($val === '#' ? '#' : $val);
                } else {
                    $resultRow[] = '';
                }
            }

            $result[] = $resultRow;
        }

        return $result;
    }

    /**
     * Parse clues from both simple [number, text] and dict formats.
     *
     * @return array{0: array<int, array{number: int, clue: string}>, 1: array<int, array{number: int, clue: string}>}
     */
    private function parseClues(array $clues): array
    {
        $across = $this->parseClueList($clues['Across'] ?? []);
        $down = $this->parseClueList($clues['Down'] ?? []);

        return [$across, $down];
    }

    /**
     * @return array<int, array{number?: int, clue: string, cells?: array}>
     */
    private function parseClueList(array $clueList): array
    {
        $result = [];

        foreach ($clueList as $entry) {
            if (is_array($entry) && array_is_list($entry) && count($entry) >= 2) {
                $result[] = [
                    'number' => (int) $entry[0],
                    'clue' => (string) $entry[1],
                ];
            } elseif (is_array($entry) && isset($entry['number'])) {
                $result[] = [
                    'number' => (int) $entry['number'],
                    'clue' => (string) ($entry['clue'] ?? ''),
                ];
            } elseif (is_array($entry) && isset($entry['label'], $entry['clue'])) {
                // Label-based format: { "label": "2-3", "clue": "...", "cells": [[col, row], ...] }
                $parsed = [
                    'clue' => (string) $entry['clue'],
                ];
                if (isset($entry['cells']) && is_array($entry['cells']) && count($entry['cells']) > 0) {
                    // Store the first cell (col, row in ipuz) so we can map to a numbered slot later
                    $parsed['cells'] = $entry['cells'];
                }
                $result[] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Renumber the grid and align clues with computed slots.
     *
     * @return array{grid: array, cluesAcross: array, cluesDown: array}
     */
    private function renumber(array $grid, int $width, int $height, array $cluesAcross, array $cluesDown): array
    {
        $result = $this->numberer->number($grid, $width, $height);

        $finalAcross = $this->matchCluesToSlots($cluesAcross, $result['across'], $result['grid']);
        $finalDown = $this->matchCluesToSlots($cluesDown, $result['down'], $result['grid']);

        return [
            'grid' => $result['grid'],
            'cluesAcross' => $finalAcross,
            'cluesDown' => $finalDown,
        ];
    }

    /**
     * Match parsed clues to numbered slots, supporting both number-keyed and cell-position-based clues.
     *
     * @return array<int, array{number: int, clue: string}>
     */
    private function matchCluesToSlots(array $clues, array $slots, array $numberedGrid): array
    {
        // Build a lookup: "row,col" => clue text (from cell-position-based clues)
        $cellMap = [];
        // Build a lookup: number => clue text (from number-keyed clues)
        $numberMap = [];

        foreach ($clues as $clue) {
            if (isset($clue['number'])) {
                $numberMap[$clue['number']] = $clue['clue'];
            } elseif (isset($clue['cells']) && count($clue['cells']) > 0) {
                // ipuz cells are [col, row], convert to "row,col" for the first cell
                $firstCell = $clue['cells'][0];
                $key = $firstCell[1].','.$firstCell[0];
                $cellMap[$key] = $clue['clue'];
            }
        }

        $final = [];
        foreach ($slots as $slot) {
            $clueText = '';

            // Try number-based match first
            if (isset($numberMap[$slot['number']])) {
                $clueText = $numberMap[$slot['number']];
            }

            // Try cell-position-based match
            $posKey = $slot['row'].','.$slot['col'];
            if ($clueText === '' && isset($cellMap[$posKey])) {
                $clueText = $cellMap[$posKey];
            }

            $final[] = [
                'number' => $slot['number'],
                'clue' => $clueText,
            ];
        }

        return $final;
    }

    /**
     * Extract metadata fields not stored as top-level columns.
     *
     * @return array<string, mixed>
     */
    private function extractMetadata(array $data): array
    {
        $topLevel = ['version', 'kind', 'dimensions', 'puzzle', 'solution', 'clues',
            'title', 'author', 'copyright', 'notes'];

        $metadata = [];

        foreach ($data as $key => $value) {
            if (! in_array($key, $topLevel)) {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }
}
