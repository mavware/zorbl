<?php

namespace Database\Seeders;

use App\Models\Word;
use App\Services\SpreadWordlistImporter;
use App\Support\CuratedPhraseList;
use Illuminate\Database\Seeder;

class WordListSeeder extends Seeder
{
    /**
     * Seed the words table from the generated word list file, then score it
     * from Spread the Wordlist when that file is present. Without it, only the
     * curated phrases are layered in so idioms, sayings, and short-word combos
     * are still preferred over dull dictionary words.
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

        if (file_exists(SpreadWordlistImporter::defaultPath())) {
            $this->command->info('Scoring words from Spread the Wordlist...');

            $result = app(SpreadWordlistImporter::class)->import();

            $this->command->info("Scored {$result['imported']} words; {$result['unlisted']} not in the list kept at the unvetted score.");

            return;
        }

        $this->seedPhrases($now);
    }

    /**
     * Seed curated phrases, sayings, idioms, and short-word combos at the
     * lively score so the grid filler favors them. Upserted after the base
     * word list so the lively score wins when a phrase collides with a plain word.
     */
    private function seedPhrases(\DateTimeInterface $now): void
    {
        $words = CuratedPhraseList::words();

        if ($words === []) {
            return;
        }

        $this->command->info('Seeding crossword phrases...');

        Word::upsert(array_map(fn (string $word): array => [
            'word' => $word,
            'length' => strlen($word),
            'score' => Word::SCORE_LIVELY,
            'created_at' => $now,
            'updated_at' => $now,
        ], $words), ['word'], ['length', 'score', 'updated_at']);

        $this->command->info('Seeded '.count($words).' phrases.');
    }
}
