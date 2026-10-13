<?php

namespace App\Services\PuzzleFeeds;

use App\Enums\PuzzlePublication;
use App\Support\PublishedPuzzle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The Guardian's Quick crossword, addressed by issue number. Every puzzle
 * page is also served as JSON, and the series page lists the latest issues.
 *
 * Clues end in a letter-count enumeration, "(6,4)", which is stripped, and
 * are cleaned of Quick conventions by {@see clues()}. Answers that span
 * several entries ("See 1 across") carry only part of the solution on each,
 * so those entries are left out.
 */
class GuardianQuickFeed implements PuzzleFeed
{
    public const string PUZZLE_URL = 'https://www.theguardian.com/crosswords/quick/';

    public const string SERIES_URL = 'https://www.theguardian.com/crosswords/series/quick';

    public function initialCursor(): string
    {
        $body = $this->request()->get(self::SERIES_URL)->throw()->body();

        preg_match_all('#/crosswords/quick/(\d+)#', $body, $matches);

        if ($matches[1] === []) {
            throw new UnreadablePuzzleException('The Guardian series page lists no quick crosswords.');
        }

        return (string) max(array_map(intval(...), $matches[1]));
    }

    public function fetch(string $cursor): ?PublishedPuzzle
    {
        $response = $this->request()->get(self::PUZZLE_URL.$cursor.'.json');

        if ($response->notFound()) {
            return null;
        }

        $crossword = $response->throw()->json('crossword');

        if (! is_array($crossword)) {
            throw new UnreadablePuzzleException("Guardian quick crossword {$cursor} has no puzzle data.");
        }

        if (! ($crossword['solutionAvailable'] ?? false)) {
            return null;
        }

        $publishedOn = is_int($crossword['date'] ?? null)
            ? CarbonImmutable::createFromTimestampMs($crossword['date'])->startOfDay()
            : today();

        return new PublishedPuzzle(
            PuzzlePublication::GuardianQuick,
            $publishedOn,
            (string) ($crossword['name'] ?? "Quick crossword No {$cursor}"),
            self::entries($crossword['entries'] ?? []),
        );
    }

    public function nextCursor(string $cursor): string
    {
        return (string) ((int) $cursor + 1);
    }

    /**
     * @param  array<int, mixed>  $rawEntries
     * @return list<array{answer: string, clue: string}>
     */
    private static function entries(array $rawEntries): array
    {
        $entries = [];

        foreach ($rawEntries as $entry) {
            if (! is_array($entry) || count($entry['group'] ?? []) > 1) {
                continue;
            }

            $answer = strtoupper((string) ($entry['solution'] ?? ''));

            if ($answer === '') {
                continue;
            }

            foreach (self::clues((string) ($entry['clue'] ?? '')) as $clue) {
                $entries[] = ['answer' => $answer, 'clue' => $clue];
            }
        }

        return $entries;
    }

    /**
     * Turn one Quick clue into standalone definition clues. The letter count
     * goes; a dash-separated double definition ("Felicity – Second Coming
     * event") becomes one clue per definition; an anagram half ("introduce
     * (anag)") is dropped; and optional leading words in brackets are removed,
     * so "(The) key facts" becomes "Key facts".
     *
     * @return list<string>
     */
    public static function clues(string $clue): array
    {
        $parts = preg_split('/\s+[–—-]\s+/u', self::stripEnumeration($clue)) ?: [];
        $clues = [];

        foreach ($parts as $part) {
            if (preg_match('/\(\s*anag(?:ram)?\s*\)\s*$/iu', $part)) {
                continue;
            }

            $part = trim((string) preg_replace('/^\([^)]*\)\s*/u', '', trim($part)));

            if ($part !== '') {
                $clues[] = mb_strtoupper(mb_substr($part, 0, 1)).mb_substr($part, 1);
            }
        }

        return $clues;
    }

    /**
     * Drop the trailing letter count: "Cocktail (6,4)" becomes "Cocktail".
     */
    public static function stripEnumeration(string $clue): string
    {
        return trim((string) preg_replace('/\s*\(\s*\d+(?:\s*[,\-]\s*\d+)*\s*\)\s*$/', '', trim($clue)));
    }

    /**
     * Retries connection failures and server errors; a 404 means the issue
     * isn't out yet and comes back as-is.
     */
    private function request(): PendingRequest
    {
        return Http::withUserAgent(config('app.name').'/1.0 ('.config('app.url').')')
            ->timeout(30)
            ->retry(3, 1000, fn (Throwable $e): bool => ! $e instanceof RequestException || $e->response->serverError(), throw: false);
    }
}
