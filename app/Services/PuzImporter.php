<?php

namespace App\Services;

use App\Exceptions\PuzImportException;

class PuzImporter
{
    private const HEADER_SIZE = 52;

    private const MAGIC = "ACROSS&DOWN\0";

    public function __construct(private GridNumberer $numberer) {}

    /**
     * Parse a .puz file's binary contents into an array suitable for Crossword::create().
     *
     * @return array<string, mixed>
     *
     * @throws PuzImportException
     */
    public function import(string $contents): array
    {
        if (strlen($contents) < self::HEADER_SIZE) {
            throw new PuzImportException('File is too short to be a valid .puz file');
        }

        $header = $this->parseHeader($contents);

        $width = $header['width'];
        $height = $header['height'];
        $numClues = $header['numClues'];
        $boardSize = $width * $height;

        $minSize = self::HEADER_SIZE + (2 * $boardSize);
        if (strlen($contents) < $minSize) {
            throw new PuzImportException('File is too short for the specified grid dimensions');
        }

        [$grid, $solution] = $this->parseSolutionBoard($contents, self::HEADER_SIZE, $width, $height);

        $stringsOffset = self::HEADER_SIZE + (2 * $boardSize);
        $strings = $this->parseStrings($contents, $stringsOffset, $numClues);

        $result = $this->numberer->number($grid, $width, $height);
        [$cluesAcross, $cluesDown] = $this->orderClues($strings['clues'], $result['across'], $result['down']);

        $styles = $this->parseExtensions($contents, $strings['endOffset'], $width, $height);

        return [
            'title' => $strings['title'] ?: null,
            'author' => $strings['author'] ?: null,
            'copyright' => $strings['copyright'] ?: null,
            'notes' => $strings['notes'] ?: null,
            'width' => $width,
            'height' => $height,
            'kind' => 'http://ipuz.org/crossword#1',
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $cluesAcross,
            'clues_down' => $cluesDown,
            'styles' => $styles ?: null,
            'metadata' => null,
        ];
    }

    /**
     * @return array{width: int, height: int, numClues: int}
     *
     * @throws PuzImportException
     */
    private function parseHeader(string $contents): array
    {
        $magic = substr($contents, 0x02, 12);
        if ($magic !== self::MAGIC) {
            throw new PuzImportException('Not a valid .puz file (missing ACROSS&DOWN signature)');
        }

        $scrambledTag = unpack('v', substr($contents, 0x32, 2))[1];
        if ($scrambledTag !== 0) {
            throw new PuzImportException('Scrambled .puz files are not supported');
        }

        $width = ord($contents[0x2C]);
        $height = ord($contents[0x2D]);
        $numClues = unpack('v', substr($contents, 0x2E, 2))[1];

        if ($width < 1 || $height < 1) {
            throw new PuzImportException('Invalid grid dimensions');
        }

        return [
            'width' => $width,
            'height' => $height,
            'numClues' => $numClues,
        ];
    }

    /**
     * Parse the solution board into grid and solution arrays.
     *
     * @return array{0: array<int, array<int, mixed>>, 1: array<int, array<int, string>>}
     */
    private function parseSolutionBoard(string $contents, int $offset, int $width, int $height): array
    {
        $grid = [];
        $solution = [];

        for ($row = 0; $row < $height; $row++) {
            $gridRow = [];
            $solRow = [];

            for ($col = 0; $col < $width; $col++) {
                $byte = $contents[$offset + ($row * $width) + $col];

                if ($byte === '.') {
                    $gridRow[] = '#';
                    $solRow[] = '#';
                } else {
                    $gridRow[] = 0;
                    $solRow[] = strtoupper($byte);
                }
            }

            $grid[] = $gridRow;
            $solution[] = $solRow;
        }

        return [$grid, $solution];
    }

    /**
     * Parse null-terminated strings from the string table.
     *
     * @return array{title: string, author: string, copyright: string, clues: array<int, string>, notes: string, endOffset: int}
     */
    private function parseStrings(string $contents, int $offset, int $numClues): array
    {
        $strings = [];
        $pos = $offset;
        $needed = 3 + $numClues + 1; // title, author, copyright, clues, notes

        for ($i = 0; $i < $needed; $i++) {
            $end = strpos($contents, "\0", $pos);

            if ($end === false) {
                // Remaining content is the last string (no trailing null)
                $strings[] = substr($contents, $pos);
                $pos = strlen($contents);

                break;
            }

            $strings[] = substr($contents, $pos, $end - $pos);
            $pos = $end + 1;
        }

        $title = $this->convertEncoding($strings[0] ?? '');
        $author = $this->convertEncoding($strings[1] ?? '');
        $copyright = $this->convertEncoding($strings[2] ?? '');

        $clues = [];
        for ($i = 0; $i < $numClues; $i++) {
            $clues[] = $this->convertEncoding($strings[3 + $i] ?? '');
        }

        $notes = $this->convertEncoding($strings[3 + $numClues] ?? '');

        return [
            'title' => $title,
            'author' => $author,
            'copyright' => $copyright,
            'clues' => $clues,
            'notes' => $notes,
            'endOffset' => $pos,
        ];
    }

    /**
     * Order .puz clues (sequenced by number, across before down) into separate across/down arrays.
     *
     * @return array{0: array<int, array{number: int, clue: string}>, 1: array<int, array{number: int, clue: string}>}
     */
    private function orderClues(array $rawClues, array $acrossSlots, array $downSlots): array
    {
        // Build the expected ordering: collect all clue numbers, then for each
        // number output across first (if exists) then down (if exists).
        $acrossNumbers = [];
        foreach ($acrossSlots as $slot) {
            $acrossNumbers[$slot['number']] = true;
        }

        $downNumbers = [];
        foreach ($downSlots as $slot) {
            $downNumbers[$slot['number']] = true;
        }

        // Collect all unique numbers in order
        $allNumbers = [];
        foreach (array_merge($acrossSlots, $downSlots) as $slot) {
            $allNumbers[$slot['number']] = true;
        }
        ksort($allNumbers);

        // Map each raw clue to the correct direction and number
        $clueIndex = 0;
        $acrossClues = [];
        $downClues = [];

        foreach (array_keys($allNumbers) as $number) {
            if (isset($acrossNumbers[$number])) {
                $acrossClues[] = [
                    'number' => $number,
                    'clue' => $rawClues[$clueIndex] ?? '',
                ];
                $clueIndex++;
            }

            if (isset($downNumbers[$number])) {
                $downClues[] = [
                    'number' => $number,
                    'clue' => $rawClues[$clueIndex] ?? '',
                ];
                $clueIndex++;
            }
        }

        return [$acrossClues, $downClues];
    }

    /**
     * Parse extension sections after the string table.
     *
     * @return array<string, array<string, mixed>>
     */
    private function parseExtensions(string $contents, int $offset, int $width, int $height): array
    {
        $styles = [];
        $boardSize = $width * $height;

        while ($offset + 8 <= strlen($contents)) {
            $sectionName = substr($contents, $offset, 4);
            $dataLength = unpack('v', substr($contents, $offset + 4, 2))[1];
            // skip checksum at offset + 6 (2 bytes)
            $dataStart = $offset + 8;

            if ($dataStart + $dataLength > strlen($contents)) {
                break;
            }

            $data = substr($contents, $dataStart, $dataLength);

            if ($sectionName === 'GEXT' && $dataLength === $boardSize) {
                for ($i = 0; $i < $boardSize; $i++) {
                    $flags = ord($data[$i]);

                    if ($flags & 0x80) {
                        $row = intdiv($i, $width);
                        $col = $i % $width;
                        $styles["{$row},{$col}"] = ['shapebg' => 'circle'];
                    }
                }
            }

            // Move past data + null terminator
            $offset = $dataStart + $dataLength + 1;
        }

        return $styles;
    }

    /**
     * Convert ISO-8859-1 string to UTF-8.
     */
    private function convertEncoding(string $str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
}
