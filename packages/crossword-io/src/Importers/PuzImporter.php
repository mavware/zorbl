<?php

namespace Zorbl\CrosswordIO\Importers;

use Zorbl\CrosswordIO\Exceptions\PuzImportException;
use Zorbl\CrosswordIO\GridNumberer;

class PuzImporter
{
    private const int HEADER_SIZE = 52;

    private const string MAGIC = "ACROSS&DOWN\0";

    private const int OFFSET_MAGIC = 0x02;

    private const int OFFSET_SCRAMBLE_TAG = 0x32;

    private const int OFFSET_WIDTH = 0x2C;

    private const int OFFSET_HEIGHT = 0x2D;

    private const int OFFSET_NUM_CLUES = 0x2E;

    private const int GEXT_CIRCLE_FLAG = 0x80;

    public function __construct(private readonly GridNumberer $numberer) {}

    /**
     * Parse a .puz file's binary contents into a plain array.
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

        [$grid, $solution] = $this->parseSolutionBoard($contents, $width, $height);

        $stringsOffset = self::HEADER_SIZE + (2 * $boardSize);
        $strings = $this->parseStrings($contents, $stringsOffset, $numClues);

        $result = $this->numberer->number($grid, $width, $height);
        [$cluesAcross, $cluesDown] = $this->orderClues($strings['clues'], $result['across'], $result['down']);

        $extensions = $this->parseExtensions($contents, $strings['endOffset'], $width, $height);
        $styles = $extensions['styles'];
        $rebus = $extensions['rebus'];

        foreach ($rebus as $row => $cols) {
            foreach ($cols as $col => $value) {
                $solution[$row][$col] = $value;
            }
        }

        return [
            'title' => $strings['title'] ?: null,
            'author' => $strings['author'] ?: null,
            'copyright' => $strings['copyright'] ?: null,
            'notes' => $strings['notes'] ?: null,
            'width' => $width,
            'height' => $height,
            'kind' => 'https://ipuz.org/crossword#1',
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
        $magic = substr($contents, self::OFFSET_MAGIC, 12);
        if ($magic !== self::MAGIC) {
            throw new PuzImportException('Not a valid .puz file (missing ACROSS&DOWN signature)');
        }

        $scrambledTag = unpack('v', substr($contents, self::OFFSET_SCRAMBLE_TAG, 2))[1];
        if ($scrambledTag !== 0) {
            throw new PuzImportException('Scrambled .puz files are not supported');
        }

        $width = ord($contents[self::OFFSET_WIDTH]);
        $height = ord($contents[self::OFFSET_HEIGHT]);
        $numClues = unpack('v', substr($contents, self::OFFSET_NUM_CLUES, 2))[1];

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
     * @return array{0: array<int, array<int, mixed>>, 1: array<int, array<int, string>>}
     */
    private function parseSolutionBoard(string $contents, int $width, int $height): array
    {
        $grid = [];
        $solution = [];

        for ($row = 0; $row < $height; $row++) {
            $gridRow = [];
            $solRow = [];

            for ($col = 0; $col < $width; $col++) {
                $byte = $contents[self::HEADER_SIZE + ($row * $width) + $col];

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
     * @return array{title: string, author: string, copyright: string, clues: array<int, string>, notes: string, endOffset: int}
     */
    private function parseStrings(string $contents, int $offset, int $numClues): array
    {
        $strings = [];
        $pos = $offset;
        $needed = 3 + $numClues + 1;

        for ($i = 0; $i < $needed; $i++) {
            $end = strpos($contents, "\0", $pos);

            if ($end === false) {
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
     * @return array{0: array<int, array{number: int, clue: string}>, 1: array<int, array{number: int, clue: string}>}
     */
    private function orderClues(array $rawClues, array $acrossSlots, array $downSlots): array
    {
        $acrossNumbers = [];
        foreach ($acrossSlots as $slot) {
            $acrossNumbers[$slot['number']] = true;
        }

        $downNumbers = [];
        foreach ($downSlots as $slot) {
            $downNumbers[$slot['number']] = true;
        }

        $allNumbers = array_keys($acrossNumbers + $downNumbers);
        sort($allNumbers);

        $clueIndex = 0;
        $acrossClues = [];
        $downClues = [];

        foreach ($allNumbers as $number) {
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
     * @return array{styles: array<string, array<string, mixed>>, rebus: array<int, array<int, string>>}
     */
    private function parseExtensions(string $contents, int $offset, int $width, int $height): array
    {
        $styles = [];
        $extensions = [];
        $rebusData = [];
        $boardSize = $width * $height;

        while ($offset + 8 <= strlen($contents)) {
            $sectionName = substr($contents, $offset, 4);
            $dataLength = unpack('v', substr($contents, $offset + 4, 2))[1];
            $dataStart = $offset + 8;

            if ($dataStart + $dataLength > strlen($contents)) {
                break;
            }

            $data = substr($contents, $dataStart, $dataLength);

            if ($sectionName === 'GEXT' && $dataLength === $boardSize) {
                for ($i = 0; $i < $boardSize; $i++) {
                    $flags = ord($data[$i]);

                    if ($flags & self::GEXT_CIRCLE_FLAG) {
                        $row = intdiv($i, $width);
                        $col = $i % $width;
                        $styles["$row,$col"] = ['shapebg' => 'circle'];
                    }
                }
            }

            if ($sectionName === 'GRBS' && $dataLength === $boardSize) {
                $extensions['GRBS'] = $data;
            }

            if ($sectionName === 'RTBL') {
                $extensions['RTBL'] = $data;
            }

            $offset = $dataStart + $dataLength + 1;
        }

        if (isset($extensions['GRBS'], $extensions['RTBL'])) {
            $rebusTable = $this->parseRebusTable($extensions['RTBL']);
            $rebusGrid = $extensions['GRBS'];

            for ($i = 0; $i < $boardSize; $i++) {
                $index = ord($rebusGrid[$i]);
                if ($index > 0 && isset($rebusTable[$index - 1])) {
                    $row = intdiv($i, $width);
                    $col = $i % $width;
                    $rebusData[$row][$col] = strtoupper($rebusTable[$index - 1]);
                }
            }
        }

        return ['styles' => $styles, 'rebus' => $rebusData ?? []];
    }

    /**
     * @return array<int, string>
     */
    private function parseRebusTable(string $data): array
    {
        $table = [];
        $entries = explode(';', rtrim($data, "\0"));

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parts = explode(':', $entry, 2);
            if (count($parts) === 2) {
                $table[(int) trim($parts[0])] = trim($parts[1]);
            }
        }

        return $table;
    }

    private function convertEncoding(string $str): string
    {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
}
