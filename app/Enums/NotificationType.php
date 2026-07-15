<?php

namespace App\Enums;

enum NotificationType: string
{
    case PuzzleCompleted = 'puzzle_completed';
    case CrosswordLiked = 'crossword_liked';
    case NewFollower = 'new_follower';
    case NewPuzzlePublished = 'new_puzzle_published';
    case NewPuzzleComment = 'new_puzzle_comment';
    case WeeklyDigest = 'weekly_digest';
    case PuzzleMilestone = 'puzzle_milestone';

    public function label(): string
    {
        return match ($this) {
            self::PuzzleCompleted => __('Puzzle completions'),
            self::CrosswordLiked => __('Puzzle likes'),
            self::NewFollower => __('New followers'),
            self::NewPuzzlePublished => __('New puzzles from followed constructors'),
            self::NewPuzzleComment => __('Puzzle comments'),
            self::WeeklyDigest => __('Weekly activity digest'),
            self::PuzzleMilestone => __('Puzzle solve milestones'),
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
            self::WeeklyDigest => __('A weekly email summarizing activity on your published puzzles'),
            self::PuzzleMilestone => __('When one of your puzzles reaches a solve milestone (10, 25, 50, 100, etc.)'),
        };
    }
}
