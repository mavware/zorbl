<?php

namespace App\Enums;

enum NotificationType: string
{
    case PuzzleCompleted = 'puzzle_completed';
    case CrosswordLiked = 'crossword_liked';
    case NewFollower = 'new_follower';
    case NewPuzzlePublished = 'new_puzzle_published';
    case NewPuzzleComment = 'new_puzzle_comment';

    public function label(): string
    {
        return match ($this) {
            self::PuzzleCompleted => __('Puzzle completions'),
            self::CrosswordLiked => __('Puzzle likes'),
            self::NewFollower => __('New followers'),
            self::NewPuzzlePublished => __('New puzzles from followed constructors'),
            self::NewPuzzleComment => __('Puzzle comments'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PuzzleCompleted => __('When someone completes one of your puzzles'),
            self::CrosswordLiked => __('When someone likes one of your puzzles'),
            self::NewFollower => __('When someone starts following you'),
            self::NewPuzzlePublished => __('When a constructor you follow publishes a new puzzle'),
            self::NewPuzzleComment => __('When someone comments on one of your puzzles'),
        };
    }
}
