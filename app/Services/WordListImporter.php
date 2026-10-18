<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\Word;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Loads the bundled word list, database/data/wordlist.txt, into the words
 * table. The list is the single source the grid filler's dictionary is built
 * from: Spread the Wordlist (spreadthewordlist.com, CC BY-NC-SA 4.0) scored
 * by how lively each entry is as fill (55 lively, 40 to 45 acceptable, 30 and
 * below poor), with the original dictionary's words folded in at the unvetted
 * score and the curated phrases at the lively score.
 *
 * Every listed word takes its list score. Words already in the table that the
 * list doesn't know are kept but scored as unvetted so the filler only reaches
 * for them when nothing better fits. Words with a vetted source of their own,
 * published puzzles and puzzles built here, keep at least that source's floor.
 */
class WordListImporter
{
    public const int MIN_WORD_LENGTH = 3;

    public const int MAX_WORD_LENGTH = 21;

    private const int BATCH_SIZE = 1000;

    public static function defaultPath(): string
    {
        return database_path('data/wordlist.txt');
    }

    /**
     * @return array{entries: int, imported: int, skipped: int, unlisted: int} `unlisted` counts words kept at the unvetted score because the list doesn't know them.
     *
     * @throws InvalidArgumentException when the file can't be read.
     */
    public function import(?string $path = null): array
    {
        $path ??= self::defaultPath();

        if (! is_readable($path)) {
            throw new InvalidArgumentException("Word list file not found: {$path}");
        }

        return DB::transaction(function () use ($path): array {
            // Everything starts unvetted; the list and the vetted sources below raise what they know.
            Word::query()->update(['score' => Word::SCORE_UNVETTED]);

            $counts = $this->importList($path);

            $unlisted = Word::query()->where('score', Word::SCORE_UNVETTED)->count() - $counts['imported_unvetted'];
            unset($counts['imported_unvetted']);

            $this->raiseFloor($this->answersFromPublications(), Word::SCORE_PUBLISHED);
            $this->raiseFloor($this->answersFromUserPuzzles(), Word::SCORE_ACCEPTABLE);

            return $counts + ['unlisted' => $unlisted];
        });
    }

    /**
     * Each line is a tab-separated `WORD score`. Entries outside the grid
     * length range or scored 0 are skipped.
     *
     * @return array{entries: int, imported: int, skipped: int, imported_unvetted: int} `imported_unvetted` counts list entries at the unvetted score.
     */
    private function importList(string $path): array
    {
        $handle = fopen($path, 'r');
        $now = now();
        $batch = [];
        $entries = 0;
        $imported = 0;
        $skipped = 0;
        $importedUnvetted = 0;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $entries++;
            $parsed = self::parseLine($line);

            if ($parsed === null) {
                $skipped++;

                continue;
            }

            $batch[] = $parsed + ['created_at' => $now, 'updated_at' => $now];
            $imported++;

            if ($parsed['score'] === Word::SCORE_UNVETTED) {
                $importedUnvetted++;
            }

            if (count($batch) >= self::BATCH_SIZE) {
                Word::upsert($batch, ['word'], ['length', 'score', 'updated_at']);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            Word::upsert($batch, ['word'], ['length', 'score', 'updated_at']);
        }

        return ['entries' => $entries, 'imported' => $imported, 'skipped' => $skipped, 'imported_unvetted' => $importedUnvetted];
    }

    /**
     * @return array{word: string, length: int, score: float}|null
     */
    public static function parseLine(string $line): ?array
    {
        if (! preg_match('/^([A-Za-z]+)\t(\d+(?:\.\d+)?)$/', $line, $matches)) {
            return null;
        }

        $word = strtoupper($matches[1]);
        $length = strlen($word);
        $score = (float) $matches[2];

        if ($length < self::MIN_WORD_LENGTH || $length > self::MAX_WORD_LENGTH || $score <= 0) {
            return null;
        }

        return ['word' => $word, 'length' => $length, 'score' => $score];
    }

    /**
     * Raise every listed word to at least $floor; words already scored higher are left alone.
     *
     * @param  list<string>  $words
     */
    private function raiseFloor(array $words, float $floor): void
    {
        foreach (array_chunk($words, self::BATCH_SIZE) as $chunk) {
            Word::query()
                ->whereIn('word', $chunk)
                ->where('score', '<', $floor)
                ->update(['score' => $floor]);
        }
    }

    /**
     * Answers scraped from professionally published crosswords.
     *
     * @return list<string>
     */
    private function answersFromPublications(): array
    {
        return ClueEntry::query()->whereNotNull('source')->distinct()->pluck('answer')->all();
    }

    /**
     * Answers from puzzles constructors published here.
     *
     * @return list<string>
     */
    private function answersFromUserPuzzles(): array
    {
        return ClueEntry::query()->whereNotNull('crossword_id')->distinct()->pluck('answer')->all();
    }
}
