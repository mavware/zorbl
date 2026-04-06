<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use App\Services\DifficultyRater;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use ZipArchive;
use Zorbl\CrosswordIO\GridNumberer;

class ActivitySeeder extends Seeder
{
    private const DOWNLOAD_URL = 'https://xd.saul.pw/xd-puzzles.zip';

    private const PUZZLE_COUNT = 30;

    private const CONSTRUCTOR_COUNT = 5;

    private const SOLVER_COUNT = 45;

    public function run(): void
    {
        if (User::where('email', 'solver1@example.com')->exists()) {
            $this->command->info('Activity data already seeded. Skipping.');

            return;
        }

        DB::disableQueryLog();
        DB::statement('PRAGMA journal_mode=WAL');
        DB::statement('PRAGMA synchronous=OFF');

        $now = now();
        $hashedPassword = Hash::make('password');
        $faker = fake();

        // Step 1: Download and parse puzzles
        $this->command->info('Downloading and parsing crossword puzzles...');
        $puzzles = $this->loadPuzzles();

        if (count($puzzles) < 5) {
            $this->command->error('Not enough valid puzzles found. Need at least 5.');

            return;
        }

        $this->command->info('Parsed '.count($puzzles).' puzzles.');

        // Step 2: Create users
        $this->command->info('Creating users...');
        $constructorEmails = [];
        $solverEmails = [];
        $userBatch = [];

        for ($i = 1; $i <= self::CONSTRUCTOR_COUNT; $i++) {
            $email = "constructor{$i}@example.com";
            $constructorEmails[] = $email;
            $userBatch[] = [
                'name' => $faker->name(),
                'email' => $email,
                'password' => $hashedPassword,
                'email_verified_at' => $now,
                'current_streak' => 0,
                'longest_streak' => 0,
                'last_solve_date' => null,
                'created_at' => $now->copy()->subDays(random_int(30, 90)),
                'updated_at' => $now,
            ];
        }

        for ($i = 1; $i <= self::SOLVER_COUNT; $i++) {
            $email = "solver{$i}@example.com";
            $solverEmails[] = $email;
            $userBatch[] = [
                'name' => $faker->name(),
                'email' => $email,
                'password' => $hashedPassword,
                'email_verified_at' => $now,
                'current_streak' => random_int(0, 15),
                'longest_streak' => random_int(1, 30),
                'last_solve_date' => $now->copy()->subDays(random_int(0, 7))->toDateString(),
                'created_at' => $now->copy()->subDays(random_int(1, 60)),
                'updated_at' => $now,
            ];
        }

        User::insert($userBatch);

        $constructorIds = User::whereIn('email', $constructorEmails)->pluck('id')->all();
        $solverIds = User::whereIn('email', $solverEmails)->pluck('id')->all();
        $allUserIds = array_merge($constructorIds, $solverIds);

        $this->command->info('Created '.(self::CONSTRUCTOR_COUNT + self::SOLVER_COUNT).' users.');

        // Step 3: Create crosswords
        $this->command->info('Creating crosswords...');
        $rater = new DifficultyRater;
        $crosswordIds = [];

        $seedPuzzles = array_slice($puzzles, 0, self::PUZZLE_COUNT);

        foreach ($seedPuzzles as $puzzle) {
            $crossword = Crossword::create([
                'user_id' => $constructorIds[array_rand($constructorIds)],
                'title' => $puzzle['title'] ?? 'Untitled Puzzle',
                'author' => $puzzle['author'] ?? null,
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

            $crosswordIds[] = $crossword->id;
        }

        $this->command->info('Created '.count($crosswordIds).' crosswords.');

        // Step 4: Create puzzle attempts
        $this->command->info('Creating puzzle attempts...');
        $attemptBatch = [];
        $attemptKeys = [];

        foreach ($solverIds as $userId) {
            $numAttempts = random_int(1, 5);
            $puzzlePool = $crosswordIds;
            shuffle($puzzlePool);

            for ($j = 0; $j < $numAttempts && $j < count($puzzlePool); $j++) {
                $crosswordId = $puzzlePool[$j];
                $key = "{$userId}-{$crosswordId}";

                if (isset($attemptKeys[$key])) {
                    continue;
                }

                $attemptKeys[$key] = true;
                $isCompleted = random_int(1, 100) <= 70;
                $solveTime = random_int(120, 3600);
                $startedAt = $now->copy()->subHours(random_int(1, 720));

                $attemptBatch[] = [
                    'user_id' => $userId,
                    'crossword_id' => $crosswordId,
                    'progress' => json_encode([]),
                    'is_completed' => $isCompleted,
                    'started_at' => $startedAt,
                    'completed_at' => $isCompleted ? $startedAt->copy()->addSeconds($solveTime) : null,
                    'solve_time_seconds' => $isCompleted ? $solveTime : null,
                    'created_at' => $startedAt,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($attemptBatch, 500) as $chunk) {
            PuzzleAttempt::insert($chunk);
        }

        $this->command->info('Created '.count($attemptBatch).' puzzle attempts.');

        // Step 5: Create comments/ratings
        $this->command->info('Creating comments and ratings...');
        $commentBatch = [];
        $commentKeys = [];
        $completedAttempts = array_values(array_filter($attemptBatch, fn ($a) => $a['is_completed']));
        shuffle($completedAttempts);

        $commentCount = min(30, count($completedAttempts));

        for ($i = 0; $i < $commentCount; $i++) {
            $attempt = $completedAttempts[$i];
            $key = "{$attempt['user_id']}-{$attempt['crossword_id']}";

            if (isset($commentKeys[$key])) {
                continue;
            }

            $commentKeys[$key] = true;
            $commentBatch[] = [
                'user_id' => $attempt['user_id'],
                'crossword_id' => $attempt['crossword_id'],
                'body' => $faker->sentence(),
                'rating' => $faker->randomElement([3, 4, 4, 5, 5, 5, 3, 4, 2, 5]),
                'created_at' => $attempt['completed_at'],
                'updated_at' => $now,
            ];
        }

        PuzzleComment::insert($commentBatch);
        $this->command->info('Created '.count($commentBatch).' comments.');

        // Step 6: Create likes
        $this->command->info('Creating likes...');
        $likeBatch = [];
        $likeKeys = [];

        for ($i = 0; $i < 50; $i++) {
            $userId = $allUserIds[array_rand($allUserIds)];
            $crosswordId = $crosswordIds[array_rand($crosswordIds)];
            $key = "{$userId}-{$crosswordId}";

            if (isset($likeKeys[$key])) {
                continue;
            }

            $likeKeys[$key] = true;
            $likeBatch[] = [
                'user_id' => $userId,
                'crossword_id' => $crosswordId,
                'created_at' => $now->copy()->subHours(random_int(1, 500)),
                'updated_at' => $now,
            ];
        }

        CrosswordLike::insert($likeBatch);
        $this->command->info('Created '.count($likeBatch).' likes.');

        // Step 7: Create follows
        $this->command->info('Creating follows...');
        $followBatch = [];
        $followKeys = [];

        for ($i = 0; $i < 30; $i++) {
            $followerId = $solverIds[array_rand($solverIds)];
            $followingId = random_int(1, 100) <= 70
                ? $constructorIds[array_rand($constructorIds)]
                : $allUserIds[array_rand($allUserIds)];

            if ($followerId === $followingId) {
                continue;
            }

            $key = "{$followerId}-{$followingId}";

            if (isset($followKeys[$key])) {
                continue;
            }

            $followKeys[$key] = true;
            $followBatch[] = [
                'follower_id' => $followerId,
                'following_id' => $followingId,
                'created_at' => $now->copy()->subDays(random_int(1, 30)),
                'updated_at' => $now,
            ];
        }

        Follow::insert($followBatch);
        $this->command->info('Created '.count($followBatch).' follows.');

        // Step 8: Create achievements
        $this->command->info('Creating achievements...');
        $achievementBatch = [];
        $solversWithCompletions = collect($completedAttempts)->pluck('user_id')->unique()->take(20);

        foreach ($solversWithCompletions as $userId) {
            $achievementBatch[] = [
                'user_id' => $userId,
                'type' => 'first_solve',
                'label' => 'First Solve',
                'description' => 'Completed your first crossword puzzle',
                'icon' => 'trophy',
                'earned_at' => $now->copy()->subDays(random_int(1, 30)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($constructorIds as $constructorId) {
            $achievementBatch[] = [
                'user_id' => $constructorId,
                'type' => 'constructor',
                'label' => 'Constructor',
                'description' => 'Published your first crossword puzzle',
                'icon' => 'pencil-square',
                'earned_at' => $now->copy()->subDays(random_int(10, 60)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Achievement::insert($achievementBatch);
        $this->command->info('Created '.count($achievementBatch).' achievements.');

        // Step 9: Create contests
        $this->command->info('Creating contests...');
        $contestCreator = $constructorIds[0];

        $activeContest = Contest::factory()->active()->featured()->create(['user_id' => $contestCreator]);
        $upcomingContest = Contest::factory()->upcoming()->create(['user_id' => $contestCreator]);

        $contestPuzzles = array_slice($crosswordIds, 0, 5);

        foreach ($contestPuzzles as $i => $crosswordId) {
            $activeContest->crosswords()->attach($crosswordId, [
                'sort_order' => $i + 1,
                'extraction_hint' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $upcomingPuzzles = array_slice($crosswordIds, 5, 3);

        foreach ($upcomingPuzzles as $i => $crosswordId) {
            $upcomingContest->crosswords()->attach($crosswordId, [
                'sort_order' => $i + 1,
                'extraction_hint' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $entryBatch = [];
        $entrants = array_slice($solverIds, 0, 15);

        foreach ($entrants as $i => $userId) {
            $puzzlesCompleted = random_int(0, count($contestPuzzles));
            $entryBatch[] = [
                'contest_id' => $activeContest->id,
                'user_id' => $userId,
                'registered_at' => $now->copy()->subDays(random_int(0, 1)),
                'total_solve_time_seconds' => $puzzlesCompleted > 0 ? random_int(300, 5400) : null,
                'puzzles_completed' => $puzzlesCompleted,
                'meta_answer' => null,
                'meta_solved' => false,
                'meta_submitted_at' => null,
                'meta_attempts_count' => 0,
                'rank' => $puzzlesCompleted > 0 ? $i + 1 : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ContestEntry::insert($entryBatch);
        $this->command->info('Created 2 contests with '.count($entryBatch).' entries.');
        $this->command->info('Activity seeding complete!');
    }

    /**
     * Download xd-puzzles.zip and parse a selection of .xd files.
     * Caches parsed results for the SimulateActivity command.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadPuzzles(): array
    {
        $cachePath = storage_path('app/private/xd-puzzles-parsed.json');

        if (file_exists($cachePath)) {
            $this->command->info('Using cached parsed puzzles.');

            return json_decode(file_get_contents($cachePath), true);
        }

        $zipPath = storage_path('app/private/xd-puzzles.zip');

        if (! file_exists($zipPath)) {
            $this->command->info('Downloading xd-puzzles.zip (~12MB)...');

            $dir = dirname($zipPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $context = stream_context_create(['http' => ['timeout' => 300]]);
            $result = @copy(self::DOWNLOAD_URL, $zipPath, $context);

            if (! $result || ! file_exists($zipPath)) {
                $this->command->error('Failed to download xd-puzzles.zip.');

                return [];
            }

            $this->command->info('Download complete.');
        }

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            $this->command->error('Failed to open zip file.');

            return [];
        }

        $xdFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (str_ends_with($name, '.xd') && ! str_contains($name, '__MACOSX')) {
                $xdFiles[] = $name;
            }
        }

        $this->command->info('Found '.count($xdFiles).' .xd files in archive.');

        shuffle($xdFiles);
        $numberer = new GridNumberer;
        $puzzles = [];

        // Parse more than needed so the scheduled command has a pool to draw from
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

            if ($parsed !== null) {
                $puzzles[] = $parsed;
            }
        }

        $zip->close();
        @unlink($zipPath);

        // Cache for both the seeder and the scheduled command
        file_put_contents($cachePath, json_encode($puzzles));

        return $puzzles;
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

        // Metadata
        $metadata = [];

        foreach (explode("\n", $sections[0]) as $line) {
            if (preg_match('/^(\w+):\s*(.+)$/', $line, $m)) {
                $metadata[strtolower($m[1])] = trim($m[2]);
            }
        }

        // Grid
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

        // Clues
        $clueLines = explode("\n", $sections[2]);
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
            'width' => $width,
            'height' => $height,
            'grid' => $result['grid'],
            'solution' => $solution,
            'clues_across' => $cluesAcross,
            'clues_down' => $cluesDown,
        ];
    }
}
