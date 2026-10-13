<?php

namespace App\Services\PuzzleFeeds;

use RuntimeException;

/**
 * The feed answered, but not with a puzzle the scraper can use. The scraper
 * skips the puzzle and moves on so one bad issue can't stall the feed.
 */
class UnreadablePuzzleException extends RuntimeException {}
