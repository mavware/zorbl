<?php

namespace App\Console\Commands;

use App\Models\Crossword;
use App\Services\DifficultyRater;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crosswords:rate')]
#[Description('Recalculate difficulty ratings for all published puzzles')]
class RecalculateDifficultyRatings extends Command
{
    public function handle(DifficultyRater $rater): int
    {
        $puzzles = Crossword::where('is_published', true)
            ->withAvg('attempts as avg_solve_time', 'solve_time_seconds')
            ->get();

        $count = 0;

        foreach ($puzzles as $puzzle) {
            $avgTime = $puzzle->avg_solve_time ? (float) $puzzle->avg_solve_time : null;
            $rating = $rater->rate($puzzle, $avgTime);

            $puzzle->update([
                'difficulty_score' => $rating['score'],
                'difficulty_label' => $rating['label'],
            ]);

            $count++;
        }

        $this->info("Rated {$count} puzzles.");

        return self::SUCCESS;
    }
}
