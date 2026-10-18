<?php

namespace App\Support;

/**
 * The hand-picked phrases, sayings, idioms, and short-word combos in
 * database/data/crossword-phrases.txt, normalized to grid answers.
 */
class CuratedPhraseList
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 21;

    public static function path(): string
    {
        return database_path('data/crossword-phrases.txt');
    }

    /**
     * Entries are normalized to letters only (e.g. "on it" -> ONIT); blank
     * lines, comments, and entries outside the grid length range are dropped.
     *
     * @return list<string>
     */
    public static function words(): array
    {
        $path = self::path();

        if (! file_exists($path)) {
            return [];
        }

        $words = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $word = strtoupper(preg_replace('/[^A-Za-z]/', '', $line));
            $length = strlen($word);

            if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
                continue;
            }

            $words[$word] = true;
        }

        return array_keys($words);
    }
}
