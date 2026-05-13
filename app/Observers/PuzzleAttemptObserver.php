<?php

namespace App\Observers;

use App\Models\PuzzleAttempt;

class PuzzleAttemptObserver
{
    public function created(PuzzleAttempt $attempt): void
    {
        $attempt->crossword->refreshSolveStats();
    }

    public function updated(PuzzleAttempt $attempt): void
    {
        $attempt->crossword->refreshSolveStats();
    }

    public function deleted(PuzzleAttempt $attempt): void
    {
        $attempt->crossword->refreshSolveStats();
    }
}
