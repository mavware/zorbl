<?php

namespace Database\Seeders;

use App\Models\Word;
use Illuminate\Database\Seeder;

class WordListSeeder extends Seeder
{
    /**
     * Seed the words table from the generated word list file.
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
    }
}
