<?php

namespace App\Enums;

use App\Services\PuzzleFeeds\GuardianQuickFeed;
use App\Services\PuzzleFeeds\NewsdayFeed;
use App\Services\PuzzleFeeds\PuzzleFeed;

/**
 * Publications whose completed crosswords the clue scraper reads.
 */
enum PuzzlePublication: string
{
    case Newsday = 'newsday';
    case GuardianQuick = 'guardian-quick';

    public function label(): string
    {
        return match ($this) {
            self::Newsday => 'Newsday',
            self::GuardianQuick => 'The Guardian Quick',
        };
    }

    /**
     * @return class-string<PuzzleFeed>
     */
    public function feedClass(): string
    {
        return match ($this) {
            self::Newsday => NewsdayFeed::class,
            self::GuardianQuick => GuardianQuickFeed::class,
        };
    }
}
