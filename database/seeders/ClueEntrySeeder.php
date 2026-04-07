<?php

namespace Database\Seeders;

use App\Models\ClueEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class ClueEntrySeeder extends Seeder
{
    private const DOWNLOAD_URL = 'https://xd.saul.pw/xd-clues.zip';

    private const BATCH_SIZE = 1000;

    /**
     * Seed the clue library from the xd crossword corpus.
     *
     * The corpus contains ~7.6M answer-clue pairs from published American crosswords.
     * Source: https://xd.saul.pw/data
     */
    public function run(): void
    {
        $admin = User::role('Admin')->first()
            ?? User::where('email', 'michael@zorbl.com')->first();

        if (! $admin) {
            throw new RuntimeException('Admin user not found. Run DatabaseSeeder first.');
        }

        $zipPath = storage_path('app/private/xd-clues.zip');
        $tsvPath = storage_path('app/private/xd-clues.tsv');

        if (! $this->ensureDataFile($zipPath, $tsvPath)) {
            throw new RuntimeException('Failed to download or extract clues data file.');
        }

        $this->command->info('Removing existing standalone clue entries...');
        ClueEntry::whereNull('crossword_id')->delete();

        $this->command->info('Importing clues from xd corpus...');
        ini_set('memory_limit', '2G');
        DB::disableQueryLog();

        $handle = fopen($tsvPath, 'r');
        $batch = [];
        $count = 0;
        $skipped = 0;
        $now = now();
        $seen = [];

        // Skip header line
        fgets($handle);

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line, 4);

            if (count($parts) < 4) {
                $skipped++;

                continue;
            }

            [, , $answer, $clue] = $parts;
            $answer = strtoupper(trim($answer));
            $clue = trim($clue);

            // Validate answer: 2-50 alpha characters only
            if (! preg_match('/^[A-Z]{2,50}$/', $answer)) {
                $skipped++;

                continue;
            }

            // Validate clue length: 2-500 characters
            if (strlen($clue) < 2 || strlen($clue) > 500) {
                $skipped++;

                continue;
            }

            // Deduplicate by answer+clue
            $key = $answer.'|'.$clue;

            if (isset($seen[$key])) {
                $skipped++;

                continue;
            }

            $seen[$key] = true;

            $batch[] = [
                'answer' => $answer,
                'clue' => $clue,
                'user_id' => $admin->id,
                'crossword_id' => null,
                'direction' => null,
                'clue_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                ClueEntry::insert($batch);
                $count += count($batch);
                $batch = [];

                if ($count % 100_000 === 0) {
                    $this->command->info("  Imported {$count} clues...");
                }
            }
        }

        if (! empty($batch)) {
            ClueEntry::insert($batch);
            $count += count($batch);
        }

        fclose($handle);

        // Free memory
        $seen = [];

        $this->command->info("Imported {$count} unique clue entries ({$skipped} skipped).");
    }

    /**
     * Download and extract the clues TSV if not already present.
     */
    private function ensureDataFile(string $zipPath, string $tsvPath): bool
    {
        if (file_exists($tsvPath)) {
            $this->command->info('Using cached clues TSV file.');

            return true;
        }

        if (! file_exists($zipPath)) {
            $this->command->info('Downloading xd-clues.zip (~84MB)...');

            $dir = dirname($zipPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $context = stream_context_create(['http' => ['timeout' => 300]]);
            $result = @copy(self::DOWNLOAD_URL, $zipPath, $context);

            if (! $result || ! file_exists($zipPath)) {
                $this->command->error('Failed to download xd-clues.zip. Check your internet connection.');

                return false;
            }

            $this->command->info('Download complete.');
        }

        $this->command->info('Extracting clues TSV...');

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->command->error('Failed to open zip file.');

            return false;
        }

        // The TSV is at xd/clues.tsv inside the zip
        $stream = $zip->getStream('xd/clues.tsv');

        if (! $stream) {
            $zip->close();
            $this->command->error('clues.tsv not found in zip archive.');

            return false;
        }

        $out = fopen($tsvPath, 'w');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        $zip->close();

        // Clean up zip to save disk space
        unlink($zipPath);

        $this->command->info('Extraction complete.');

        return true;
    }
}
