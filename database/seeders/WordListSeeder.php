<?php

namespace Database\Seeders;

use App\Console\Commands\GenerateWordList;
use App\Models\Word;
use Illuminate\Database\Seeder;

class WordListSeeder extends Seeder
{
    /**
     * Score bonus applied to curated phrases so the grid filler prefers this
     * lively fill over dull dictionary words at the same slot.
     */
    private const float PHRASE_SCORE_BONUS = 25.0;

    /**
     * Seed the words table from the generated word list file, then layer in the
     * curated phrases so idioms, sayings, and short-word combos are preferred.
     */
    public function run(): void
    {
        $path = database_path('data/wordlist.txt');

        if (! file_exists($path)) {
            $this->command->error('Word list file not found. Run: php artisan crossword:generate-wordlist');

            return;
        }

        $this->command->info('Seeding words table...');

        $handle = fopen($path, 'r');
        $batch = [];
        $count = 0;
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$word, $score] = explode("\t", $line, 2);

            $batch[] = [
                'word' => $word,
                'length' => strlen($word),
                'score' => (float) $score,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                Word::upsert($batch, ['word'], ['length', 'score', 'updated_at']);
                $batch = [];
                $count += 1000;
            }
        }

        if (! empty($batch)) {
            Word::upsert($batch, ['word'], ['length', 'score', 'updated_at']);
            $count += count($batch);
        }

        fclose($handle);

        $this->command->info("Seeded {$count} words.");

        $this->seedPhrases($now);
    }

    /**
     * Seed curated phrases, sayings, idioms, and short-word combos.
     *
     * Entries are normalized to letters only (e.g. "on it" -> ONIT) and scored
     * with a liveliness bonus so the grid filler favors them. Upserted after the
     * base word list so the bonus wins when a phrase collides with a plain word.
     */
    private function seedPhrases(\DateTimeInterface $now): void
    {
        $path = database_path('data/crossword-phrases.txt');

        if (! file_exists($path)) {
            return;
        }

        $this->command->info('Seeding crossword phrases...');

        $batch = [];
        $count = 0;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $word = strtoupper(preg_replace('/[^A-Za-z]/', '', $line));
            $length = strlen($word);

            // Grid entries are 3-21 letters, matching the base word list.
            if ($length < 3 || $length > 21) {
                continue;
            }

            $score = round(GenerateWordList::calculateScore($word) + self::PHRASE_SCORE_BONUS, 2);

            $batch[] = [
                'word' => $word,
                'length' => $length,
                'score' => $score,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $count++;
        }

        if (! empty($batch)) {
            Word::upsert($batch, ['word'], ['length', 'score', 'updated_at']);
        }

        $this->command->info("Seeded {$count} phrases.");
    }
}
