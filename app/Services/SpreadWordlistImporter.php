<?php

namespace App\Services;

use App\Models\ClueEntry;
use App\Models\Word;
use App\Support\CuratedPhraseList;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rescores the word list from Spread the Wordlist (spreadthewordlist.com,
 * CC BY-NC-SA 4.0), a crossword word list scored by how lively each entry
 * is as fill: 55 is lively, 40 to 45 is acceptable, 30 and below is poor.
 *
 * Every listed entry takes its Spread score. Words the list doesn't know are
 * kept but scored as unvetted so the grid filler only reaches for them when
 * nothing better fits. Words with a vetted source of their own, published
 * puzzles and the curated phrase list, keep at least that source's floor.
 */
class SpreadWordlistImporter
{
    public const int MIN_WORD_LENGTH = 3;

    public const int MAX_WORD_LENGTH = 21;

    private const int BATCH_SIZE = 1000;

    public static function defaultPath(): string
    {
        return database_path('data/spreadthewordlist.txt');
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
            throw new InvalidArgumentException("Spread the Wordlist file not found: {$path}");
        }

        return DB::transaction(function () use ($path): array {
            // Everything starts unvetted; the list and the vetted sources below raise what they know.
            Word::query()->update(['score' => Word::SCORE_UNVETTED]);

            $counts = $this->importList($path);

            $unlisted = Word::query()->where('score', Word::SCORE_UNVETTED)->count() - $counts['imported_unvetted'];
            unset($counts['imported_unvetted']);

            $this->raiseFloor(CuratedPhraseList::words(), Word::SCORE_LIVELY, insertMissing: true);
            $this->raiseFloor($this->answersFromPublications(), Word::SCORE_PUBLISHED);
            $this->raiseFloor($this->answersFromUserPuzzles(), Word::SCORE_ACCEPTABLE);

            return $counts + ['unlisted' => $unlisted];
        });
    }

    /**
     * Each line is `WORD;score`. Entries outside the grid length range are
     * skipped, as are entries scored 0, which the list marks as unusable.
     *
     * @return array{entries: int, imported: int, skipped: int, imported_unvetted: int} `imported_unvetted` counts list entries that happen to share the unvetted score.
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
        if (! preg_match('/^([A-Za-z]+);(\d+)$/', $line, $matches)) {
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
    private function raiseFloor(array $words, float $floor, bool $insertMissing = false): void
    {
        $now = now();

        foreach (array_chunk($words, self::BATCH_SIZE) as $chunk) {
            if ($insertMissing) {
                Word::insertOrIgnore(array_map(fn (string $word): array => [
                    'word' => $word,
                    'length' => strlen($word),
                    'score' => $floor,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

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
