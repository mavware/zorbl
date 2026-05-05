<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case PuzzleCompleted = 'puzzle.completed';
    case PuzzleAttemptStarted = 'puzzle.attempt.started';
    case PuzzleLiked = 'puzzle.liked';
    case PuzzleCommented = 'puzzle.commented';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PuzzleCompleted->value => 'Puzzle Completed',
            self::PuzzleAttemptStarted->value => 'Puzzle Attempt Started',
            self::PuzzleLiked->value => 'Puzzle Liked',
            self::PuzzleCommented->value => 'Puzzle Commented',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
