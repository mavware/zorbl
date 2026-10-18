<?php

namespace App\Services;

use App\Enums\PuzzlePublication;
use App\Models\ClueEntry;
use App\Models\User;
use App\Models\Word;
use App\Services\PuzzleFeeds\PuzzleFeed;
use App\Services\PuzzleFeeds\UnreadablePuzzleException;
use App\Support\PublishedPuzzle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads completed crosswords from trusted publications and files their
 * answer/clue pairs in the clue library, attributed to the admin and pending
 * review unless approved up front. Answers the word list doesn't have yet
 * are added with the same bonus as words from puzzles published here, since
 * a professional editor has already vetted them.
 *
 * Each publication's position is saved after every puzzle so a run continues
 * where the last one stopped.
 */
class PublishedClueScraper
{
    public const int DEFAULT_PUZZLES = 3;

    public const int MIN_WORD_LENGTH = 3;

    /**
     * An answer a professional publication ran is good fill, so words start
     * at this floor and are raised to it; better scores are left alone.
     */
    public const float SCORE_FLOOR = Word::SCORE_PUBLISHED;

    /**
     * @return array{fetched: int, inserted: int, skipped: int, caught_up: bool}
     *
     * @throws ConnectionException|RequestException when the publication can't be reached.
     * @throws RuntimeException when no Admin user exists to attribute clues to.
     */
    public function scrape(PuzzlePublication $publication, int $puzzles = self::DEFAULT_PUZZLES, bool $approve = false): array
    {
        $admin = $this->attributionUser();
        $feed = $this->feed($publication);
        $cursorKey = $this->cursorKey($publication);
        $cursor = Cache::get($cursorKey);

        if ($cursor === null) {
            $cursor = $feed->initialCursor();
            Cache::forever($cursorKey, $cursor);
        }

        $fetched = 0;
        $inserted = 0;
        $skipped = 0;
        $caughtUp = false;

        while ($fetched + $skipped < max(1, $puzzles)) {
            try {
                $puzzle = $feed->fetch($cursor);
            } catch (UnreadablePuzzleException $e) {
                Log::warning('Skipped an unreadable published puzzle', ['publication' => $publication->value, 'cursor' => $cursor, 'error' => $e->getMessage()]);
                $skipped++;
                $cursor = $feed->nextCursor($cursor);
                Cache::forever($cursorKey, $cursor);

                continue;
            }

            if ($puzzle === null) {
                $caughtUp = true;

                break;
            }

            $fetched++;
            $inserted += $this->store($puzzle, $admin, $approve);
            $cursor = $feed->nextCursor($cursor);
            Cache::forever($cursorKey, $cursor);
        }

        return ['fetched' => $fetched, 'inserted' => $inserted, 'skipped' => $skipped, 'caught_up' => $caughtUp];
    }

    /**
     * Forget the saved position so the next run starts over from the feed's
     * latest puzzle.
     */
    public function restart(PuzzlePublication $publication): void
    {
        Cache::forget($this->cursorKey($publication));
    }

    /**
     * Returns how many clues were new to the library.
     */
    private function store(PublishedPuzzle $puzzle, User $admin, bool $approve): int
    {
        $now = now();
        $rows = [];

        foreach ($puzzle->entries as $entry) {
            $answer = $entry['answer'];

            if (! preg_match('/^[A-Z]+$/', $answer) || strlen($answer) < self::MIN_WORD_LENGTH) {
                continue;
            }

            $clue = AiWordClueWriter::sanitize([$entry['clue']], $answer, 1)[0] ?? null;

            if ($clue === null) {
                continue;
            }

            $rows[$answer.'|'.$clue] = [
                'answer' => $answer,
                'clue' => $clue,
                'quality_issues' => ClueEntry::encodedQualityIssues($clue, $answer),
                'crossword_id' => null,
                'user_id' => $admin->id,
                'direction' => null,
                'clue_number' => null,
                'source' => $puzzle->publication->value,
                'source_date' => $puzzle->publishedOn->toDateString(),
                'status' => $approve ? ClueEntry::STATUS_APPROVED : ClueEntry::STATUS_PENDING,
                'reviewed_by' => $approve ? $admin->id : null,
                'reviewed_at' => $approve ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        $this->syncWords(array_column($rows, 'answer'));

        return ClueEntry::insertOrIgnore(array_values($rows));
    }

    /**
     * @param  list<string>  $answers
     */
    private function syncWords(array $answers): void
    {
        $answers = array_values(array_unique($answers));

        if ($answers === []) {
            return;
        }

        $now = now();

        Word::insertOrIgnore(array_map(fn (string $word): array => [
            'word' => $word,
            'length' => strlen($word),
            'score' => self::SCORE_FLOOR,
            'created_at' => $now,
            'updated_at' => $now,
        ], $answers));

        Word::query()
            ->whereIn('word', $answers)
            ->where('score', '<', self::SCORE_FLOOR)
            ->update(['score' => self::SCORE_FLOOR]);
    }

    private function feed(PuzzlePublication $publication): PuzzleFeed
    {
        return app($publication->feedClass());
    }

    private function cursorKey(PuzzlePublication $publication): string
    {
        return 'published-clue-scrape:cursor:'.$publication->value;
    }

    private function attributionUser(): User
    {
        $admin = User::role('Admin')->first();

        if ($admin === null) {
            throw new RuntimeException('No Admin role user found to attribute scraped clues to.');
        }

        return $admin;
    }
}
