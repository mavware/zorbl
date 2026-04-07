<?php

namespace App\Console\Commands;

use App\Models\ClueEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crossword:generate-wordlist {--source= : Path to source dictionary file (optional, falls back to clue entries)}')]
#[Description('Generate a curated crossword word list from a system dictionary or clue entries')]
class GenerateWordList extends Command
{
    /**
     * English letter frequencies for scoring crossword-worthiness.
     *
     * @var array<string, float>
     */
    private const array LETTER_FREQ = [
        'E' => 12.7, 'T' => 9.1, 'A' => 8.2, 'O' => 7.5, 'I' => 7.0,
        'N' => 6.7, 'S' => 6.3, 'H' => 6.1, 'R' => 6.0, 'D' => 4.3,
        'L' => 4.0, 'C' => 2.8, 'U' => 2.8, 'M' => 2.4, 'W' => 2.4,
        'F' => 2.2, 'G' => 2.0, 'Y' => 2.0, 'P' => 1.9, 'B' => 1.5,
        'V' => 1.0, 'K' => 0.8, 'J' => 0.15, 'X' => 0.15, 'Q' => 0.10,
        'Z' => 0.07,
    ];

    public function handle(): int
    {
        $source = $this->option('source');

        // Auto-detect: use source file if provided/exists, otherwise fall back to clue entries
        if ($source !== null && ! file_exists($source)) {
            $this->error("Source dictionary not found: {$source}");

            return self::FAILURE;
        }

        if ($source === null) {
            $defaultPath = '/usr/share/dict/words';

            if (file_exists($defaultPath)) {
                $source = $defaultPath;
            }
        }

        if ($source !== null) {
            $words = $this->generateFromDictionary($source);
        } else {
            $words = $this->generateFromClueEntries();
        }

        if (empty($words)) {
            $this->error('No words generated. Ensure clue entries have been seeded or provide a dictionary file.');

            return self::FAILURE;
        }

        $this->writeWordList($words);

        return self::SUCCESS;
    }

    /**
     * Generate word list from a system dictionary file, boosted by known clue entries.
     *
     * @return array<string, float>
     */
    private function generateFromDictionary(string $source): array
    {
        $this->info("Reading dictionary from {$source}...");

        $knownWords = ClueEntry::query()
            ->distinct()
            ->pluck('answer')
            ->map(fn (string $w) => mb_strtoupper($w))
            ->flip()
            ->all();

        $lines = file($source, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $words = [];

        foreach ($lines as $line) {
            $word = trim($line);

            // Skip proper nouns (start with uppercase in the source dictionary)
            if ($word === '' || ctype_upper($word[0])) {
                continue;
            }

            // Only alpha characters
            if (! ctype_alpha($word)) {
                continue;
            }

            $upper = strtoupper($word);
            $length = strlen($upper);

            // Crossword words are 3-21 letters
            if ($length < 3 || $length > 21) {
                continue;
            }

            $score = self::calculateScore($upper);

            // Bonus for known crossword words
            if (isset($knownWords[$upper])) {
                $score += 5.0;
            }

            $words[$upper] = round($score, 2);
        }

        $this->info('Filtered to '.count($words).' words from dictionary.');

        return $words;
    }

    /**
     * Generate word list purely from clue entry answers in the database.
     *
     * @return array<string, float>
     */
    private function generateFromClueEntries(): array
    {
        $this->info('No dictionary file found. Generating word list from clue entries...');

        $count = ClueEntry::count();

        if ($count === 0) {
            return [];
        }

        $this->info("Processing {$count} clue entries...");

        $words = [];

        ClueEntry::select('answer')
            ->selectRaw('count(*) as frequency')
            ->groupBy('answer')
            ->orderByDesc('frequency')
            ->chunk(5000, function ($rows) use (&$words) {
                foreach ($rows as $row) {
                    $upper = mb_strtoupper($row->answer);
                    $length = mb_strlen($upper);

                    if ($length < 3 || $length > 21) {
                        continue;
                    }

                    // Only alpha characters
                    if (! ctype_alpha($upper)) {
                        continue;
                    }

                    $score = self::calculateScore($upper);

                    // Frequency bonus: more appearances = more crossword-worthy
                    $freqBonus = min(5.0, log1p($row->frequency) * 1.5);
                    $score += $freqBonus;

                    $words[$upper] = round($score, 2);
                }
            });

        $this->info('Generated '.count($words).' words from clue entries.');

        return $words;
    }

    /**
     * Write the word list to the output file.
     *
     * @param  array<string, float>  $words
     */
    private function writeWordList(array $words): void
    {
        $outputPath = database_path('data/wordlist.txt');

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $handle = fopen($outputPath, 'w');

        foreach ($words as $word => $score) {
            fwrite($handle, "{$word}\t{$score}\n");
        }

        fclose($handle);

        $this->info("Word list written to {$outputPath}");
    }

    /**
     * Calculate crossword-worthiness score based on letter frequency.
     *
     * Score = average letter frequency normalized to 0-100 scale.
     */
    public static function calculateScore(string $word): float
    {
        $length = strlen($word);

        if ($length === 0) {
            return 0;
        }

        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $sum += self::LETTER_FREQ[$word[$i]] ?? 0;
        }

        return ($sum / $length) * (100 / 12.7);
    }
}
