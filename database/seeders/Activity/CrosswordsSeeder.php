<?php

namespace Database\Seeders\Activity;

use App\Models\Crossword;
use App\Models\User;
use App\Services\DifficultyRater;
use Illuminate\Support\Facades\DB;

class CrosswordsSeeder extends BaseActivitySeeder
{
    protected function runStep(): void
    {
        $puzzles = $this->loadPuzzles();

        if (count($puzzles) < 5) {
            $this->log('Not enough valid puzzles found. Need at least 5. Got '.count($puzzles).'.', 'error');

            return;
        }

        DB::disableQueryLog();

        $seedPuzzles = array_slice($puzzles, 0, self::PUZZLE_COUNT);
        $authorNameToEmail = $this->authorEmailMap($seedPuzzles);

        $constructors = User::whereIn('email', array_values($authorNameToEmail))->get()->keyBy('name');

        if ($constructors->isEmpty()) {
            $this->log('No constructor users found. Run the users step first.', 'error');

            return;
        }

        $fallbackConstructor = $constructors->first();

        $existingTitles = Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->pluck('title')
            ->flip()
            ->all();

        $rater = new DifficultyRater;
        $created = 0;
        $skipped = 0;

        foreach ($seedPuzzles as $puzzle) {
            $title = $puzzle['title'] ?? 'Untitled Puzzle';

            if (isset($existingTitles[$title])) {
                $skipped++;

                continue;
            }

            $authorName = $this->cleanAuthorName($puzzle['author'] ?? '');
            $owner = $constructors->get($authorName) ?? $fallbackConstructor;

            // Crossword::$fillable excludes user_id by design — assigning the
            // owner via the relationship sets user_id directly on the model
            // and bypasses mass-assignment filtering.
            $crossword = $owner->crosswords()->create([
                'title' => $title,
                'author' => $authorName ?: null,
                'copyright' => $puzzle['copyright'] ?? null,
                'width' => $puzzle['width'],
                'height' => $puzzle['height'],
                'kind' => 'http://ipuz.org/crossword#1',
                'grid' => $puzzle['grid'],
                'solution' => $puzzle['solution'],
                'clues_across' => $puzzle['clues_across'],
                'clues_down' => $puzzle['clues_down'],
                'is_published' => true,
            ]);

            $rating = $rater->rate($crossword);
            $crossword->update([
                'difficulty_score' => $rating['score'],
                'difficulty_label' => $rating['label'],
            ]);

            $created++;
        }

        $this->log("Created {$created} crossword(s), skipped {$skipped} that already existed.");
    }
}
