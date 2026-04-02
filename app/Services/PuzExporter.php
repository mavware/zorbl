<?php

namespace App\Services;

use App\Models\Crossword;

class PuzExporter
{
    private const VERSION = "1.3\0";

    private const MAGIC = "ACROSS&DOWN\0";

    public function __construct(private GridNumberer $numberer) {}

    /**
     * Export a Crossword model to .puz binary format.
     */
    public function export(Crossword $crossword): string
    {
        $width = $crossword->width;
        $height = $crossword->height;

        $solutionBoard = $this->buildSolutionBoard($crossword);
        $playerState = $this->buildPlayerState($solutionBoard);
        $orderedClues = $this->orderCluesForPuz($crossword);
        $stringTable = $this->buildStringTable($crossword, $orderedClues);
        $gext = $this->buildGextSection($crossword);

        $numClues = count($orderedClues);

        // CIB: 8 bytes
        $cib = pack('CCvvv', $width, $height, $numClues, 0x0001, 0);
        $cibCksum = $this->cksum($cib);

        // Compute overall checksum
        $cksum = $cibCksum;
        $cksum = $this->cksum($solutionBoard, $cksum);
        $cksum = $this->cksum($playerState, $cksum);

        // String checksums: include non-empty metadata strings with null terminators, all clues, and notes
        $title = $this->toIso($crossword->title ?? '');
        $author = $this->toIso($crossword->author ?? '');
        $copyright = $this->toIso($crossword->copyright ?? '');
        $notes = $this->toIso($crossword->notes ?? '');

        $stringsForCksum = '';
        foreach ([$title, $author, $copyright] as $s) {
            if ($s !== '') {
                $stringsForCksum .= $s."\0";
            }
        }
        foreach ($orderedClues as $clue) {
            $stringsForCksum .= $this->toIso($clue)."\0";
        }
        if ($notes !== '') {
            $stringsForCksum .= $notes."\0";
        }
        $cksum = $this->cksum($stringsForCksum, $cksum);

        // Compute masked checksums
        $masked = $this->computeMaskedChecksums($cib, $solutionBoard, $playerState, $stringsForCksum);

        // Build header
        $header = pack('v', $cksum);
        $header .= self::MAGIC;
        $header .= pack('v', $cibCksum);
        $header .= $masked;
        $header .= self::VERSION;
        $header .= "\0\0"; // reserved
        $header .= "\0\0"; // scrambled checksum
        $header .= str_repeat("\0", 12); // reserved
        $header .= $cib;

        return $header.$solutionBoard.$playerState.$stringTable.$gext;
    }

    /**
     * Build the solution board string (width * height bytes).
     */
    private function buildSolutionBoard(Crossword $crossword): string
    {
        $board = '';

        for ($row = 0; $row < $crossword->height; $row++) {
            for ($col = 0; $col < $crossword->width; $col++) {
                $cell = $crossword->solution[$row][$col] ?? '';

                if ($cell === '#' || $cell === null || $cell === '') {
                    $board .= '.';
                } else {
                    $board .= strtoupper($cell[0]);
                }
            }
        }

        return $board;
    }

    /**
     * Build the player state string (fresh/unsolved).
     */
    private function buildPlayerState(string $solutionBoard): string
    {
        return strtr($solutionBoard, [
            ...array_fill_keys(range('A', 'Z'), '-'),
        ]);
    }

    /**
     * Order clues in .puz sequence: by number, across before down at same number.
     *
     * @return array<int, string>
     */
    private function orderCluesForPuz(Crossword $crossword): array
    {
        $result = $this->numberer->number($crossword->grid, $crossword->width, $crossword->height);

        $acrossMap = collect($crossword->clues_across)->keyBy('number');
        $downMap = collect($crossword->clues_down)->keyBy('number');

        $acrossNumbers = [];
        foreach ($result['across'] as $slot) {
            $acrossNumbers[$slot['number']] = true;
        }

        $downNumbers = [];
        foreach ($result['down'] as $slot) {
            $downNumbers[$slot['number']] = true;
        }

        $allNumbers = array_keys($acrossNumbers + $downNumbers);
        sort($allNumbers);

        $ordered = [];

        foreach ($allNumbers as $number) {
            if (isset($acrossNumbers[$number])) {
                $ordered[] = $acrossMap->get($number)['clue'] ?? '';
            }
            if (isset($downNumbers[$number])) {
                $ordered[] = $downMap->get($number)['clue'] ?? '';
            }
        }

        return $ordered;
    }

    /**
     * Build the null-terminated string table.
     */
    private function buildStringTable(Crossword $crossword, array $orderedClues): string
    {
        $table = $this->toIso($crossword->title ?? '')."\0";
        $table .= $this->toIso($crossword->author ?? '')."\0";
        $table .= $this->toIso($crossword->copyright ?? '')."\0";

        foreach ($orderedClues as $clue) {
            $table .= $this->toIso($clue)."\0";
        }

        $table .= $this->toIso($crossword->notes ?? '')."\0";

        return $table;
    }

    /**
     * Build GEXT extension section for circled cells.
     */
    private function buildGextSection(Crossword $crossword): string
    {
        $styles = $crossword->styles ?? [];

        if (empty($styles)) {
            return '';
        }

        $boardSize = $crossword->width * $crossword->height;
        $data = str_repeat("\0", $boardSize);
        $hasCircles = false;

        foreach ($styles as $key => $style) {
            if (isset($style['shapebg']) && $style['shapebg'] === 'circle') {
                [$row, $col] = explode(',', $key);
                $index = (int) $row * $crossword->width + (int) $col;

                if ($index < $boardSize) {
                    $data[$index] = chr(0x80);
                    $hasCircles = true;
                }
            }
        }

        if (! $hasCircles) {
            return '';
        }

        $cksum = $this->cksum($data);

        return 'GEXT'.pack('v', $boardSize).pack('v', $cksum).$data."\0";
    }

    /**
     * Compute masked checksums (8 bytes for offsets 0x10-0x17).
     */
    private function computeMaskedChecksums(string $cib, string $solution, string $state, string $strings): string
    {
        $mask = 'ICHEATED';

        $cibCk = $this->cksum($cib);
        $solCk = $this->cksum($solution);
        $stateCk = $this->cksum($state);
        $strCk = $this->cksum($strings);

        $lowMask = '';
        $lowMask .= chr(($cibCk & 0xFF) ^ ord($mask[0]));
        $lowMask .= chr(($solCk & 0xFF) ^ ord($mask[1]));
        $lowMask .= chr(($stateCk & 0xFF) ^ ord($mask[2]));
        $lowMask .= chr(($strCk & 0xFF) ^ ord($mask[3]));

        $highMask = '';
        $highMask .= chr((($cibCk >> 8) & 0xFF) ^ ord($mask[4]));
        $highMask .= chr((($solCk >> 8) & 0xFF) ^ ord($mask[5]));
        $highMask .= chr((($stateCk >> 8) & 0xFF) ^ ord($mask[6]));
        $highMask .= chr((($strCk >> 8) & 0xFF) ^ ord($mask[7]));

        return $lowMask.$highMask;
    }

    /**
     * CRC-16 variant checksum used by .puz format.
     */
    private function cksum(string $data, int $cksum = 0): int
    {
        for ($i = 0; $i < strlen($data); $i++) {
            if ($cksum & 0x0001) {
                $cksum = ($cksum >> 1) + 0x8000;
            } else {
                $cksum = $cksum >> 1;
            }
            $cksum = ($cksum + ord($data[$i])) & 0xFFFF;
        }

        return $cksum;
    }

    /**
     * Convert UTF-8 to ISO-8859-1 with fallback for unsupported characters.
     */
    private function toIso(string $str): string
    {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
}
