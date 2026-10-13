<?php

namespace App\Services\PuzzleFeeds;

use App\Support\PublishedPuzzle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Reads a publication's completed crosswords one at a time. A cursor names
 * the next puzzle to read (a date or an issue number, depending on how the
 * publication addresses its puzzles) and the scraper stores it between runs.
 */
interface PuzzleFeed
{
    /**
     * The cursor to start from when no run has saved one yet.
     *
     * @throws ConnectionException|RequestException
     */
    public function initialCursor(): string;

    /**
     * The puzzle at the cursor, or null when it hasn't been published yet.
     *
     * @throws ConnectionException|RequestException when the feed can't be reached.
     * @throws UnreadablePuzzleException when the puzzle exists but can't be parsed.
     */
    public function fetch(string $cursor): ?PublishedPuzzle;

    public function nextCursor(string $cursor): string;
}
