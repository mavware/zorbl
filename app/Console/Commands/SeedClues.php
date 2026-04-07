<?php

namespace App\Console\Commands;

use App\Models\ClueEntry;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

#[Signature('seed:clues')]
#[Description('Download and seed the crossword clue library')]
class SeedClues extends Command
{
    private const DOWNLOAD_URL = 'https://xd.saul.pw/xd-clues.zip';

    private const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $admin = User::role('Admin')->first();

        if (! $admin) {
            $this->error('No Admin role user found.');
            $this->line('Users in database: '.User::count());
            $first = User::first();
            $this->line('First user: '.($first ? "{$first->name} <{$first->email}>" : 'none'));

            return self::FAILURE;
        }

        $this->info("Using admin: {$admin->name} <{$admin->email}>");

        $zipPath = storage_path('app/private/xd-clues.zip');
        $tsvPath = storage_path('app/private/xd-clues.tsv');

        if (! $this->ensureDataFile($zipPath, $tsvPath)) {
            return self::FAILURE;
        }

        $fileSize = filesize($tsvPath);
        $this->info("TSV file size: {$fileSize} bytes");

        if ($fileSize < 100) {
            $this->error('TSV file appears empty or corrupt. Deleting cached file — run again to re-download.');
            unlink($tsvPath);

            return self::FAILURE;
        }

        $this->info('Removing existing standalone clue entries...');
        ClueEntry::whereNull('crossword_id')->delete();

        $this->info('Importing clues from xd corpus...');
        ini_set('memory_limit', '2G');
        DB::disableQueryLog();

        $handle = fopen($tsvPath, 'r');
        $batch = [];
        $count = 0;
        $skipped = 0;
        $now = now();
        $seen = [];

        // Skip header line
        $header = fgets($handle);
        $this->line("Header: {$header}");

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
                    $this->info("  Imported {$count} clues...");
                }
            }
        }

        if (! empty($batch)) {
            ClueEntry::insert($batch);
            $count += count($batch);
        }

        fclose($handle);
        $seen = [];

        if ($count === 0) {
            $this->error("No clues imported ({$skipped} skipped). TSV may be corrupt — delete {$tsvPath} and retry.");

            return self::FAILURE;
        }

        $this->info("Imported {$count} unique clue entries ({$skipped} skipped).");

        return self::SUCCESS;
    }

    private function ensureDataFile(string $zipPath, string $tsvPath): bool
    {
        if (file_exists($tsvPath)) {
            $this->info('Using cached clues TSV file.');

            return true;
        }

        if (! file_exists($zipPath)) {
            $this->info('Downloading xd-clues.zip (~84MB)...');

            $dir = dirname($zipPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $context = stream_context_create(['http' => ['timeout' => 300]]);
            $result = @copy(self::DOWNLOAD_URL, $zipPath, $context);

            if (! $result || ! file_exists($zipPath)) {
                $this->error('Failed to download xd-clues.zip. Check your internet connection.');

                return false;
            }

            $this->info('Download complete. Size: '.filesize($zipPath).' bytes');
        }

        $this->info('Extracting clues TSV...');

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->error('Failed to open zip file.');

            return false;
        }

        $stream = $zip->getStream('xd/clues.tsv');

        if (! $stream) {
            $zip->close();
            $this->error('clues.tsv not found in zip archive.');

            return false;
        }

        $out = fopen($tsvPath, 'w');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        $zip->close();

        unlink($zipPath);

        $this->info('Extraction complete.');

        return true;
    }
}
