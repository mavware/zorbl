<?php

namespace App\Services;

use App\Models\Crossword;
use App\Notifications\NewPuzzlePublished;
use Illuminate\Support\Facades\Notification;

/**
 * Publishes and unpublishes a puzzle, with everything that goes with it:
 * its clues enter or leave the clue library, a newly published puzzle is
 * rated for difficulty, and its constructor's followers are told about it.
 *
 * Callers decide who may do this; nothing here checks authorization.
 */
class CrosswordPublisher
{
    public function __construct(private readonly ClueHarvester $harvester, private readonly DifficultyRater $rater) {}

    public function publish(Crossword $crossword): void
    {
        $crossword->update(['is_published' => true]);

        $this->harvester->harvest($crossword);

        $rating = $this->rater->rate($crossword);
        $crossword->update([
            'difficulty_score' => $rating['score'],
            'difficulty_label' => $rating['label'],
        ]);

        $constructor = $crossword->user;
        $followers = $constructor?->followers;
        if ($followers?->isNotEmpty()) {
            Notification::send($followers, new NewPuzzlePublished($crossword, $constructor));
        }
    }

    public function unpublish(Crossword $crossword): void
    {
        $crossword->update(['is_published' => false]);

        $this->harvester->purge($crossword);
    }
}
