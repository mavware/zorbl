<?php

namespace Database\Seeders\Activity;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;
use CrosswordBuilder\CrosswordIO\GridNumberer;

abstract class BaseActivitySeeder extends Seeder
{
    protected const string DOWNLOAD_URL = 'https://xd.saul.pw/xd-puzzles.zip';

    protected const int PUZZLE_COUNT = 30;

    protected const int SOLVER_COUNT = 45;

    private const EXCLUDED_PUBLICATIONS = [
        'new york times',
        'ny times',
        'nyt',
        'la times',
        'l. a. times',
        'l.a. times',
        'los angeles times',
        'usa today',
        'washington post',
        'post puzzler',
        'newsday',
        'wall street journal',
        'wsj',
        'boston globe',
        'chicago tribune',
        'slate ',
    ];

    public function run(): void
    {
        try {
            $this->runStep();
        } catch (Throwable $e) {
            Log::error('['.class_basename(static::class).'] failed: '.$e->getMessage(), ['exception' => $e]);
            $this->command?->error('['.class_basename(static::class).'] failed: '.$e->getMessage());

            throw $e;
        }
    }

    abstract protected function runStep(): void;

    /**
     * Mirror a status message to the console and laravel.log so it surfaces
     * in Laravel Cloud's Logs tab regardless of how the seeder was invoked.
     */
    protected function log(string $message, string $level = 'info'): void
    {
        $tag = '['.class_basename(static::class).']';

        match ($level) {
            'warning' => $this->command?->warn($message),
            'error' => $this->command?->error($message),
            default => $this->command?->info($message),
        };

        Log::log($level === 'warning' ? 'warning' : $level, $tag.' '.$message);
    }

    /**
     * Filter a user-insert batch down to rows whose email is not already
     * in the users table. Lets seeders be safely re-run after a partial
     * failure without hitting unique-constraint violations.
     *
     * @param  array<int, array<string, mixed>>  $batch
     * @return array<int, array<string, mixed>>
     */
    protected function filterOutExistingEmails(array $batch): array
    {
        if ($batch === []) {
            return [];
        }

        $emails = array_column($batch, 'email');
        $existing = User::whereIn('email', $emails)->pluck('email')->flip()->all();

        return array_values(array_filter($batch, fn ($row) => ! isset($existing[$row['email']])));
    }

    /**
     * Map the seed puzzles' author names to their constructor emails.
     * Deterministic given the parsed puzzles file, so each sub-seeder
     * can independently resolve constructor users by name.
     *
     * @param  array<int, array<string, mixed>>  $seedPuzzles
     * @return array<string, string>
     */
    protected function authorEmailMap(array $seedPuzzles): array
    {
        $map = [];

        foreach ($seedPuzzles as $puzzle) {
            $name = $this->cleanAuthorName($puzzle['author'] ?? '');

            if ($name !== '' && ! isset($map[$name])) {
                $slug = preg_replace('/[^a-z0-9]+/', '.', strtolower($name));
                $map[$name] = "{$slug}@example.com";
            }
        }

        return $map;
    }

    /**
     * Load parsed puzzles from the bundled file, then the local cache,
     * then fall back to downloading + parsing the upstream zip.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadPuzzles(): array
    {
        $bundledPath = database_path('seeders/data/xd-puzzles-parsed.json');

        if (file_exists($bundledPath)) {
            $this->log('Using bundled parsed puzzles from '.$bundledPath);

            return json_decode(file_get_contents($bundledPath), true);
        }

        $cachePath = storage_path('app/private/xd-puzzles-parsed.json');

        if (file_exists($cachePath)) {
            $this->log('Using cached parsed puzzles from '.$cachePath);

            return json_decode(file_get_contents($cachePath), true);
        }

        $zipPath = storage_path('app/private/xd-puzzles.zip');

        if (! file_exists($zipPath)) {
            $this->log('Downloading xd-puzzles.zip (~183MB)...');

            $dir = dirname($zipPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $context = stream_context_create(['http' => ['timeout' => 900]]);
            $result = copy(self::DOWNLOAD_URL, $zipPath, $context);

            if (! $result || ! file_exists($zipPath)) {
                $this->log('Failed to download xd-puzzles.zip from '.self::DOWNLOAD_URL, 'error');

                return [];
            }

            $this->log('Download complete. Size: '.filesize($zipPath).' bytes');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->log('Failed to open zip file at '.$zipPath, 'error');

            return [];
        }

        $xdFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_ends_with($name, '.xd') && ! str_contains($name, '__MACOSX')) {
                $xdFiles[] = $name;
            }
        }

        $this->log('Found '.count($xdFiles).' .xd files in archive.');

        shuffle($xdFiles);
        $numberer = new GridNumberer;
        $puzzles = [];
        $targetCount = 200;

        foreach ($xdFiles as $fileName) {
            if (count($puzzles) >= $targetCount) {
                break;
            }

            $content = $zip->getFromName($fileName);

            if ($content === false) {
                continue;
            }

            $parsed = $this->parseXdFile($content, $numberer);

            if ($parsed !== null && $this->passesCopyrightFilter($parsed)) {
                $puzzles[] = $parsed;
            }
        }

        $zip->close();
        @unlink($zipPath);

        file_put_contents($cachePath, json_encode($puzzles));

        return $puzzles;
    }

    /**
     * @param  array<string, mixed>  $puzzle
     */
    private function passesCopyrightFilter(array $puzzle): bool
    {
        $title = $puzzle['title'] ?? '';
        $titleLower = strtolower($title);

        foreach (self::EXCLUDED_PUBLICATIONS as $pub) {
            if (str_contains($titleLower, $pub)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse a .xd format crossword file.
     *
     * @return array<string, mixed>|null
     */
    private function parseXdFile(string $content, GridNumberer $numberer): ?array
    {
        $sections = preg_split('/\n\n+/', trim($content));

        if (count($sections) < 3) {
            return null;
        }

        $metadata = [];

        foreach (explode("\n", $sections[0]) as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $m)) {
                $metadata[strtolower($m[1])] = trim($m[2]);
            }
        }

        $gridLines = array_filter(explode("\n", $sections[1]), fn ($l) => trim($l) !== '');
        $gridLines = array_values($gridLines);

        if (count($gridLines) < 2) {
            return null;
        }

        $height = count($gridLines);
        $width = mb_strlen($gridLines[0]);

        if ($width < 3 || $height < 3 || $width > 25 || $height > 25) {
            return null;
        }

        $solution = [];

        foreach ($gridLines as $line) {
            if (mb_strlen($line) !== $width) {
                return null;
            }

            $row = [];

            for ($i = 0; $i < $width; $i++) {
                $ch = $line[$i];

                if ($ch === '#') {
                    $row[] = '#';
                } elseif (ctype_upper($ch)) {
                    $row[] = $ch;
                } else {
                    return null;
                }
            }

            $solution[] = $row;
        }

        $plainGrid = [];

        foreach ($solution as $row) {
            $plainGrid[] = array_map(fn ($c) => $c === '#' ? '#' : 0, $row);
        }

        $result = $numberer->number($plainGrid, $width, $height);

        $clueSections = array_slice($sections, 2);
        $clueLines = explode("\n", implode("\n", $clueSections));
        $parsedClues = ['across' => [], 'down' => []];

        foreach ($clueLines as $line) {
            $line = trim($line);

            if (preg_match('/^([AD])(\d+)\.\s+(.+?)\s+~\s+(\S+)$/', $line, $m)) {
                $direction = $m[1] === 'A' ? 'across' : 'down';
                $parsedClues[$direction][(int) $m[2]] = $m[3];
            }
        }

        $cluesAcross = [];

        foreach ($result['across'] as $slot) {
            $cluesAcross[] = [
                'number' => $slot['number'],
                'clue' => $parsedClues['across'][$slot['number']] ?? '',
            ];
        }

        $cluesDown = [];

        foreach ($result['down'] as $slot) {
            $cluesDown[] = [
                'number' => $slot['number'],
                'clue' => $parsedClues['down'][$slot['number']] ?? '',
            ];
        }

        $totalSlots = count($result['across']) + count($result['down']);
        $filledClues = count(array_filter($cluesAcross, fn ($c) => $c['clue'] !== ''))
            + count(array_filter($cluesDown, fn ($c) => $c['clue'] !== ''));

        if ($totalSlots > 0 && $filledClues / $totalSlots < 0.5) {
            return null;
        }

        return [
            'title' => $metadata['title'] ?? null,
            'author' => $metadata['author'] ?? $metadata['editor'] ?? null,
            'copyright' => $metadata['copyright'] ?? null,
            'date' => $metadata['date'] ?? null,
            'width' => $width,
            'height' => $height,
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $cluesAcross,
            'clues_down' => $cluesDown,
        ];
    }

    /**
     * Clean a raw xd author string into a human name.
     */
    protected function cleanAuthorName(string $raw): string
    {
        $name = trim($raw);

        if ($name === '' || $name === 'Unknown' || $name === 'S.N.') {
            return '';
        }

        if (preg_match('/^\d+$/', $name)) {
            return '';
        }

        $name = preg_replace('/^[Bb]y\s+/', '', $name);
        $name = preg_replace('/\s*[,\/]\s*(edited by|ed\.|Edited by|Ed\.).*$/i', '', $name);
        $name = preg_replace('/\s*--.*$/', '', $name);
        $name = preg_replace('/\s*\(.*\)/', '', $name);

        if (str_contains($name, ' / ')) {
            $name = trim(explode(' / ', $name)[0]);
        }

        if (str_contains($name, ' & ')) {
            $name = trim(explode(' & ', $name)[0]);
        }

        $name = preg_replace('/^[Bb]y\s+/', '', $name);
        $name = preg_replace('/^\?\?\s*\/?\s*/', '', $name);
        $name = trim($name);

        if (mb_strlen($name) < 3 || ! preg_match('/[a-zA-Z]/', $name)) {
            return '';
        }

        return $name;
    }
}
