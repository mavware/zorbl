<?php

namespace Zorbl\CrosswordIO\Importers;

use Spatie\PdfToText\Pdf;
use Zorbl\CrosswordIO\Exceptions\PdfImportException;
use Zorbl\CrosswordIO\GridNumberer;

class PdfImporter
{
    private const string PDF_MAGIC = '%PDF-';

    public function __construct(private readonly GridNumberer $numberer) {}

    /**
     * Parse a crossword PDF's contents into a plain array.
     *
     * Uses pdftotext (via spatie/pdf-to-text) with layout preservation
     * to extract the solution grid and clues from a crossword PDF.
     *
     * @return array<string, mixed>
     *
     * @throws PdfImportException
     */
    public function import(string $contents): array
    {
        $this->validatePdf($contents);

        $tmpFile = tempnam(sys_get_temp_dir(), 'xword_').'.pdf';
        file_put_contents($tmpFile, $contents);

        try {
            $pdftotext = $this->findPdftotext();
            $pageTexts = $this->extractPages($tmpFile, $pdftotext);

            $solutionPageIdx = $this->findSolutionPage($pageTexts);
            [$acrossPageIdx, $downPageIdx] = $this->findCluePages($pageTexts, $solutionPageIdx);

            $solution = $this->parseSolutionGrid($tmpFile, $pdftotext, $solutionPageIdx);
            $width = count($solution[0]);
            $height = count($solution);

            $cluesAcross = $this->parseClues($pageTexts[$acrossPageIdx]);
            $cluesDown = $this->parseClues($pageTexts[$downPageIdx]);

            $titleAndAuthor = $this->extractTitleAndAuthor($pageTexts[0] ?? '');
            $copyright = $this->extractCopyright($pageTexts[$solutionPageIdx]);

            return $this->buildResult($solution, $width, $height, $cluesAcross, $cluesDown, $titleAndAuthor, $copyright);
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * @throws PdfImportException
     */
    private function validatePdf(string $contents): void
    {
        if (! str_starts_with($contents, self::PDF_MAGIC)) {
            throw new PdfImportException('Not a valid PDF file (missing %PDF- header).');
        }
    }

    /**
     * Locate the pdftotext binary.
     *
     * @throws PdfImportException
     */
    private function findPdftotext(): string
    {
        $paths = [
            '/opt/homebrew/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/usr/bin/pdftotext',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new PdfImportException(
            'pdftotext is not installed. Install poppler: brew install poppler (macOS) or apt-get install poppler-utils (Linux).'
        );
    }

    /**
     * Extract layout-preserved text for each page of the PDF.
     *
     * @return array<int, string>
     *
     * @throws PdfImportException
     */
    private function extractPages(string $pdfPath, string $pdftotext): array
    {
        // Get total page count by extracting all text first
        $allText = (new Pdf($pdftotext))
            ->setPdf($pdfPath)
            ->setOptions(['layout'])
            ->text();

        // Split by form feed character (page break)
        $pages = preg_split('/\f/', $allText);

        if (count($pages) < 2) {
            throw new PdfImportException('PDF has fewer than 2 pages. Expected at least a grid page and a solution page.');
        }

        return $pages;
    }

    /**
     * Find the page index containing the solution grid.
     */
    private function findSolutionPage(array $pages): int
    {
        foreach ($pages as $i => $text) {
            if (stripos($text, 'solution') !== false) {
                return $i;
            }
        }

        return count($pages) - 1;
    }

    /**
     * Find page indices for Across and Down clues.
     *
     * @return array{0: int, 1: int}
     */
    private function findCluePages(array $pages, int $solutionPageIdx): array
    {
        $acrossIdx = null;
        $downIdx = null;

        foreach ($pages as $i => $text) {
            if ($i === $solutionPageIdx) {
                continue;
            }

            $upper = strtoupper($text);

            if ($acrossIdx === null && str_contains($upper, 'ACROSS')) {
                $acrossIdx = $i;
            }

            if (str_contains($upper, 'DOWN') && $i !== $acrossIdx) {
                $downIdx = $i;
            }
        }

        return [
            $acrossIdx ?? 1,
            $downIdx ?? 2,
        ];
    }

    /**
     * Parse the solution grid using bounding-box coordinates from pdftotext.
     *
     * Uses pdftotext -bbox to get exact PDF coordinates for each character,
     * then clusters x-positions into columns and y-positions into rows.
     *
     * @return array<int, array<int, string>>
     *
     * @throws PdfImportException
     */
    private function parseSolutionGrid(string $pdfPath, string $pdftotext, int $solutionPageNum): array
    {
        $pageNum = $solutionPageNum + 1; // pdftotext uses 1-based page numbers

        $bboxXml = (new Pdf($pdftotext))
            ->setPdf($pdfPath)
            ->setOptions(['bbox', "f {$pageNum}", "l {$pageNum}"])
            ->text();

        // Extract single uppercase letter words with their coordinates
        preg_match_all(
            '/word xMin="([^"]+)" yMin="([^"]+)" xMax="[^"]+" yMax="[^"]+">([A-Z])<\/word>/',
            $bboxXml,
            $matches,
            PREG_SET_ORDER,
        );

        if (count($matches) < 4) {
            throw new PdfImportException('Could not find solution grid letters in PDF.');
        }

        $letters = [];

        foreach ($matches as $m) {
            $letters[] = ['x' => (float) $m[1], 'y' => (float) $m[2], 'letter' => $m[3]];
        }

        // Cluster x-positions into columns and y-positions into rows
        $xValues = array_map(fn (array $l) => $l['x'], $letters);
        $yValues = array_map(fn (array $l) => $l['y'], $letters);

        $columnCenters = $this->clusterPositions($xValues);
        $rowCenters = $this->clusterPositions($yValues);

        $width = count($columnCenters);
        $height = count($rowCenters);

        if ($width < 2 || $height < 2) {
            throw new PdfImportException("Grid too small: detected {$width}x{$height}.");
        }

        // Build the solution grid
        $grid = array_fill(0, $height, array_fill(0, $width, '#'));

        foreach ($letters as $l) {
            $col = $this->findNearest($l['x'], $columnCenters);
            $row = $this->findNearest($l['y'], $rowCenters);
            $grid[$row][$col] = $l['letter'];
        }

        return $grid;
    }

    /**
     * Cluster sorted numeric values into groups, returning the mean of each.
     *
     * Uses the largest jump in the sorted gap list to find the natural break
     * between within-cluster variation and between-cluster separation.
     *
     * @param  array<int, int|float>  $values
     * @return array<int, float>
     */
    private function clusterPositions(array $values): array
    {
        $unique = array_unique($values);
        sort($unique);

        if (count($unique) < 2) {
            return $unique;
        }

        // Compute gaps between consecutive unique values
        $gaps = [];
        for ($i = 1; $i < count($unique); $i++) {
            $gaps[] = $unique[$i] - $unique[$i - 1];
        }

        $sortedGaps = $gaps;
        sort($sortedGaps);

        $minGap = $sortedGaps[0];
        $maxGap = $sortedGaps[count($sortedGaps) - 1];

        // If all gaps are roughly the same size, each value is its own cluster
        if ($maxGap - $minGap < $minGap * 0.5) {
            return array_map(fn (float|int $v) => (float) $v, $unique);
        }

        // Find the natural break using the biggest jump in the sorted gap list
        $maxJump = 0;
        $tolerance = $maxGap * 0.5;

        for ($i = 1; $i < count($sortedGaps); $i++) {
            $jump = $sortedGaps[$i] - $sortedGaps[$i - 1];

            if ($jump > $maxJump) {
                $maxJump = $jump;
                $tolerance = ($sortedGaps[$i - 1] + $sortedGaps[$i]) / 2;
            }
        }

        $clusters = [[$unique[0]]];

        for ($i = 1; $i < count($unique); $i++) {
            if ($tolerance >= $unique[$i] - end($clusters[count($clusters) - 1])) {
                $clusters[count($clusters) - 1][] = $unique[$i];
            } else {
                $clusters[] = [$unique[$i]];
            }
        }

        return array_map(fn (array $c) => array_sum($c) / count($c), $clusters);
    }

    /**
     * Find the index of the nearest center to a given value.
     */
    private function findNearest(int|float $value, array $centers): int
    {
        $minDist = PHP_INT_MAX;
        $minIdx = 0;

        foreach ($centers as $i => $center) {
            $dist = abs($center - $value);

            if ($dist < $minDist) {
                $minDist = $dist;
                $minIdx = $i;
            }
        }

        return $minIdx;
    }

    /**
     * Parse clues from a page with two-column layout.
     *
     * The layout text has clues in two columns. Each clue starts with a number.
     * Multi-line clues have continuation lines indented without a leading number.
     *
     * @return array<int, array{number: int, clue: string}>
     */
    private function parseClues(string $pageText): array
    {
        $lines = explode("\n", $pageText);

        // Split each line into left and right halves based on the column gap.
        // The gap is typically around the midpoint of the page width.
        $midpoint = $this->findColumnSplit($lines);

        $leftLines = [];
        $rightLines = [];

        foreach ($lines as $line) {
            if (strlen($line) <= 1) {
                continue;
            }

            $left = mb_substr($line, 0, $midpoint);
            $right = mb_substr($line, $midpoint);

            $leftTrimmed = trim($left);
            $rightTrimmed = trim($right);

            if ($leftTrimmed !== '') {
                $leftLines[] = $leftTrimmed;
            }

            if ($rightTrimmed !== '') {
                $rightLines[] = $rightTrimmed;
            }
        }

        $allLines = array_merge($leftLines, $rightLines);

        return $this->parseClueLines($allLines);
    }

    /**
     * Find the column split point for two-column clue layout.
     *
     * Scans for the column position with the most consecutive spaces across lines,
     * searching around the midpoint of the page.
     */
    private function findColumnSplit(array $lines): int
    {
        $maxLen = 0;

        foreach ($lines as $line) {
            $maxLen = max($maxLen, strlen($line));
        }

        if ($maxLen < 10) {
            return (int) ($maxLen / 2);
        }

        // Count how many lines have a space at each column position
        // Focus on the middle third of the page
        $searchStart = (int) ($maxLen * 0.3);
        $searchEnd = (int) ($maxLen * 0.7);

        $bestCol = (int) ($maxLen / 2);
        $bestScore = 0;

        for ($col = $searchStart; $col <= $searchEnd; $col++) {
            $score = 0;

            foreach ($lines as $line) {
                if (strlen($line) <= $col) {
                    continue;
                }

                // Check if there's a run of spaces around this column
                $chunk = substr($line, max(0, $col - 1), 3);

                if (trim($chunk) === '') {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCol = $col;
            }
        }

        return $bestCol;
    }

    /**
     * Parse individual clue lines into number/text pairs.
     *
     * @return array<int, array{number: int, clue: string}>
     */
    private function parseClueLines(array $lines): array
    {
        $clues = [];
        $currentNumber = null;
        $currentText = '';

        foreach ($lines as $line) {
            // Skip section headers
            $upper = strtoupper(trim($line));

            if (in_array($upper, ['ACROSS', 'DOWN', 'MARCH', ''])) {
                continue;
            }

            // Check if line starts a new clue (number followed by text)
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $match)) {
                // Save previous clue
                if ($currentNumber !== null) {
                    $clues[] = ['number' => $currentNumber, 'clue' => trim($currentText)];
                }

                $currentNumber = (int) $match[1];
                $currentText = $match[2];
            } elseif ($currentNumber !== null) {
                // Continuation of previous clue
                $currentText .= ' '.$line;
            }
        }

        // Save last clue
        if ($currentNumber !== null) {
            $clues[] = ['number' => $currentNumber, 'clue' => trim($currentText)];
        }

        return $clues;
    }

    /**
     * Extract title and author from the first page.
     *
     * @return array{title: ?string, author: ?string}
     */
    private function extractTitleAndAuthor(string $pageText): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $pageText)), fn ($l) => $l !== '');
        $lines = array_values($lines);

        $title = $lines[0] ?? null;
        $author = null;

        foreach (array_reverse($lines) as $line) {
            if (preg_match('/^[Bb]y\s+(.+)/', $line, $match)) {
                $author = preg_replace('/\s*[-–]\s*www\..*$/', '', $match[1]);

                break;
            }
        }

        return ['title' => $title, 'author' => $author];
    }

    /**
     * Extract copyright notice from the solution page.
     */
    private function extractCopyright(string $pageText): ?string
    {
        foreach (explode("\n", $pageText) as $line) {
            $trimmed = trim($line);

            if ($trimmed !== '' && (stripos($trimmed, 'copyright') !== false || str_contains($trimmed, '©') || stripos($trimmed, 'copy authorization') !== false)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Build the standard importer result array.
     *
     * @param  array<int, array<int, string>>  $solution
     * @param  array<int, array{number: int, clue: string}>  $cluesAcross
     * @param  array<int, array{number: int, clue: string}>  $cluesDown
     * @param  array{title: ?string, author: ?string}  $titleAndAuthor
     * @return array<string, mixed>
     */
    private function buildResult(
        array $solution,
        int $width,
        int $height,
        array $cluesAcross,
        array $cluesDown,
        array $titleAndAuthor,
        ?string $copyright,
    ): array {
        // Build grid: 0 for letter cells, '#' for blocks
        $grid = [];

        for ($row = 0; $row < $height; $row++) {
            $gridRow = [];

            for ($col = 0; $col < $width; $col++) {
                $cell = $solution[$row][$col] ?? '#';
                $gridRow[] = $cell === '#' ? '#' : 0;
            }

            $grid[] = $gridRow;
        }

        $result = $this->numberer->number($grid, $width, $height);

        $matchedAcross = $this->matchClues($cluesAcross, $result['across']);
        $matchedDown = $this->matchClues($cluesDown, $result['down']);

        return [
            'title' => $titleAndAuthor['title'],
            'author' => $titleAndAuthor['author'],
            'copyright' => $copyright,
            'notes' => null,
            'width' => $width,
            'height' => $height,
            'kind' => 'https://ipuz.org/crossword#1',
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $matchedAcross,
            'clues_down' => $matchedDown,
            'styles' => null,
            'metadata' => null,
        ];
    }

    /**
     * Match extracted clues (by number) to the auto-computed slots.
     *
     * @param  array<int, array{number: int, clue: string}>  $clues
     * @param  array<int, array{number: int, row: int, col: int, length: int}>  $slots
     * @return array<int, array{number: int, clue: string}>
     */
    private function matchClues(array $clues, array $slots): array
    {
        $clueMap = [];

        foreach ($clues as $clue) {
            $clueMap[$clue['number']] = $clue['clue'];
        }

        $matched = [];

        foreach ($slots as $slot) {
            $matched[] = [
                'number' => $slot['number'],
                'clue' => $clueMap[$slot['number']] ?? '',
            ];
        }

        return $matched;
    }
}
