<?php

namespace App\Services;

use App\Exceptions\IpuzImportException;
use App\Exceptions\JpzImportException;
use App\Exceptions\PuzImportException;

readonly class ImportDetector
{
    public function __construct(
        private IpuzImporter $ipuzImporter,
        private PuzImporter $puzImporter,
        private JpzImporter $jpzImporter,
    ) {}

    /**
     * Detect the correct importer and import the puzzle contents.
     *
     * Uses a combination of file extension hints, content-sniffing, and
     * fallback attempts to handle edge cases like wrong file extensions,
     * BOM markers, or unusual encodings.
     *
     * @return array<string, mixed>
     *
     * @throws IpuzImportException|PuzImportException|JpzImportException
     */
    public function import(string $contents, string $extension = ''): array
    {
        $contents = $this->stripBom($contents);
        $extension = strtolower(trim($extension, '. '));

        // Try extension-based detection first
        $importer = $this->detectByExtension($extension);
        if ($importer !== null) {
            try {
                return $importer->import($contents);
            } catch (IpuzImportException|PuzImportException|JpzImportException $extensionError) {
                // If the extension was explicit and known, try content-sniffing
                // but if content-sniffing also fails, re-throw the original error
            }
        }

        // Content-sniffing detection
        $sniffedImporter = $this->detectByContent($contents);
        if ($sniffedImporter !== null && $sniffedImporter !== ($importer ?? null)) {
            try {
                return $sniffedImporter->import($contents);
            } catch (IpuzImportException|PuzImportException|JpzImportException) {
                // Content sniffing also failed
            }
        }

        // If extension-based detection had an error, throw it
        if (isset($extensionError)) {
            throw $extensionError;
        }

        // Last resort: try each importer
        return $this->tryAll($contents);
    }

    /**
     * Detect importer based on file extension.
     */
    private function detectByExtension(string $extension): IpuzImporter|PuzImporter|JpzImporter|null
    {
        return match ($extension) {
            'puz' => $this->puzImporter,
            'jpz' => $this->jpzImporter,
            'ipuz', 'json' => $this->ipuzImporter,
            default => null,
        };
    }

    /**
     * Detect importer based on content analysis.
     */
    private function detectByContent(string $contents): IpuzImporter|PuzImporter|JpzImporter|null
    {
        // PUZ: Check for ACROSS&DOWN magic at offset 0x02
        if (strlen($contents) >= 14 && str_contains(substr($contents, 0, 14), 'ACROSS&DOWN')) {
            return $this->puzImporter;
        }

        // JPZ: gzip magic bytes
        if (strlen($contents) >= 2 && $contents[0] === "\x1f" && $contents[1] === "\x8b") {
            return $this->jpzImporter;
        }

        // JPZ: XML declaration or root element
        $trimmed = ltrim($contents);
        if (str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<crossword-compiler')
            || str_starts_with($trimmed, '<rectangular-puzzle')) {
            return $this->jpzImporter;
        }

        // iPUZ: JSON structure with ipuz-specific keys
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, 'ipuz(')) {
            return $this->ipuzImporter;
        }

        return null;
    }

    /**
     * Try each importer in turn, returning the first successful result.
     *
     * @throws IpuzImportException
     */
    private function tryAll(string $contents): array
    {
        $lastException = null;

        foreach ([$this->ipuzImporter, $this->puzImporter, $this->jpzImporter] as $importer) {
            try {
                return $importer->import($contents);
            } catch (IpuzImportException|PuzImportException|JpzImportException $e) {
                $lastException = $e;
            }
        }

        throw new IpuzImportException(
            'Could not detect file format. '
            .($lastException !== null ? $lastException->getMessage() : 'Unknown format.')
        );
    }

    /**
     * Strip UTF-8 BOM if present.
     */
    private function stripBom(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }

        return $contents;
    }
}
