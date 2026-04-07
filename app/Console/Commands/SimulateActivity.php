<?php

namespace App\Console\Commands;

use App\Models\Achievement;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\FavoriteList;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use App\Services\DifficultyRater;
use Carbon\CarbonInterface;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('simulate:activity')]
#[Description('Simulate a small burst of realistic user activity')]
class SimulateActivity extends Command
{
    private Generator $faker;

    public function handle(): int
    {
        $this->faker = Faker::create();
        $solverCount = User::where('email', 'like', 'solver%@example.com')->count();
        $crosswordCount = Crossword::where('is_published', true)->count();

        if ($solverCount === 0 || $crosswordCount === 0) {
            $this->warn('No seeded users or crosswords found. Run ActivitySeeder first.');

            return self::FAILURE;
        }

        $now = now();

        $this->maybeCreateNewUsers($now);
        $this->maybePublishNewCrosswords($now);
        $this->createSolveActivity($now);
        $this->createSocialActivity($now);
        $this->grantAchievements($now);
        $this->maybeCreateContestActivity($now);

        $this->info('Activity simulation tick complete.');

        return self::SUCCESS;
    }

    private function maybeCreateNewUsers(CarbonInterface $now): void
    {
        if (random_int(1, 100) > 10) {
            return;
        }

        $maxSolver = User::where('email', 'like', 'solver%@example.com')
            ->selectRaw("max(cast(replace(replace(email, 'solver', ''), '@example.com', '') as unsigned)) as max_num")
            ->value('max_num') ?? 0;

        $count = random_int(1, 3);
        $hashedPassword = Hash::make('password');
        $batch = [];

        for ($i = 1; $i <= $count; $i++) {
            $num = $maxSolver + $i;
            $batch[] = [
                'name' => $this->faker->name(),
                'email' => "solver{$num}@example.com",
                'password' => $hashedPassword,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        User::insert($batch);
        $this->info("Created {$count} new solver(s).");
    }

    private function maybePublishNewCrosswords(CarbonInterface $now): void
    {
        if (random_int(1, 100) > 2) {
            return;
        }

        $cachePath = storage_path('app/private/xd-puzzles-parsed.json');

        if (! file_exists($cachePath)) {
            return;
        }

        $puzzles = json_decode(file_get_contents($cachePath), true);

        if (empty($puzzles)) {
            return;
        }

        $existingTitles = Crossword::pluck('title')->flip()->all();
        $available = array_values(array_filter($puzzles, fn ($p) => ! isset($existingTitles[$p['title'] ?? ''])));

        if (empty($available)) {
            return;
        }

        $hashedPassword = Hash::make('password');
        $count = random_int(1, 2);
        $rater = new DifficultyRater;
        $created = 0;

        for ($i = 0; $i < $count && $i < count($available); $i++) {
            $puzzle = $available[array_rand($available)];
            $authorName = $this->cleanAuthorName($puzzle['author'] ?? '');

            $userId = null;

            if ($authorName !== '') {
                $slug = preg_replace('/[^a-z0-9]+/', '.', strtolower($authorName));
                $user = User::firstOrCreate(
                    ['email' => "{$slug}@example.com"],
                    [
                        'name' => $authorName,
                        'password' => $hashedPassword,
                        'email_verified_at' => $now,
                    ]
                );
                $userId = $user->id;
            }

            if ($userId === null) {
                $userId = Crossword::where('is_published', true)->inRandomOrder()->value('user_id');
            }

            if ($userId === null) {
                continue;
            }

            $crossword = Crossword::create([
                'user_id' => $userId,
                'title' => $puzzle['title'] ?? 'Untitled Puzzle',
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

        if ($created > 0) {
            $this->info("Published {$created} new crossword(s).");
        }
    }

    private function createSolveActivity(CarbonInterface $now): void
    {
        $solverIds = User::where('email', 'like', 'solver%@example.com')
            ->inRandomOrder()
            ->limit(random_int(3, 8))
            ->pluck('id')
            ->all();

        $crosswordIds = Crossword::where('is_published', true)->pluck('id')->all();

        if (empty($crosswordIds)) {
            return;
        }

        $created = 0;

        foreach ($solverIds as $userId) {
            $attempted = PuzzleAttempt::where('user_id', $userId)->pluck('crossword_id')->flip()->all();
            $available = array_values(array_filter($crosswordIds, fn ($id) => ! isset($attempted[$id])));

            if (empty($available)) {
                continue;
            }

            $crosswordId = $available[array_rand($available)];
            $isCompleted = random_int(1, 100) <= 70;
            $solveTime = random_int(120, 3600);
            $startedAt = $now->copy()->subMinutes(random_int(5, 120));

            PuzzleAttempt::create([
                'user_id' => $userId,
                'crossword_id' => $crosswordId,
                'progress' => [],
                'is_completed' => $isCompleted,
                'started_at' => $startedAt,
                'completed_at' => $isCompleted ? $startedAt->copy()->addSeconds($solveTime) : null,
                'solve_time_seconds' => $isCompleted ? $solveTime : null,
            ]);

            if ($isCompleted) {
                $user = User::find($userId);
                $today = $now->toDateString();
                $yesterday = $now->copy()->subDay()->toDateString();

                $newStreak = $user->last_solve_date === $yesterday
                    ? $user->current_streak + 1
                    : ($user->last_solve_date === $today ? $user->current_streak : 1);

                $user->update([
                    'current_streak' => $newStreak,
                    'longest_streak' => max($user->longest_streak, $newStreak),
                    'last_solve_date' => $today,
                ]);
            }

            $created++;
        }

        if ($created > 0) {
            $this->info("Created {$created} puzzle attempt(s).");
        }
    }

    private function createSocialActivity(CarbonInterface $now): void
    {
        // Likes
        if (random_int(1, 100) <= 60) {
            $count = random_int(1, 3);
            $created = 0;

            for ($i = 0; $i < $count; $i++) {
                $completedAttempt = PuzzleAttempt::where('is_completed', true)
                    ->inRandomOrder()
                    ->first();

                if (! $completedAttempt) {
                    break;
                }

                $exists = CrosswordLike::where('user_id', $completedAttempt->user_id)
                    ->where('crossword_id', $completedAttempt->crossword_id)
                    ->exists();

                if (! $exists) {
                    CrosswordLike::create([
                        'user_id' => $completedAttempt->user_id,
                        'crossword_id' => $completedAttempt->crossword_id,
                    ]);
                    $created++;
                }
            }

            if ($created > 0) {
                $this->info("Created {$created} like(s).");
            }
        }

        // Comments
        if (random_int(1, 100) <= 40) {
            $count = random_int(1, 2);
            $created = 0;

            for ($i = 0; $i < $count; $i++) {
                $completedAttempt = PuzzleAttempt::where('is_completed', true)
                    ->inRandomOrder()
                    ->first();

                if (! $completedAttempt) {
                    break;
                }

                $exists = PuzzleComment::where('user_id', $completedAttempt->user_id)
                    ->where('crossword_id', $completedAttempt->crossword_id)
                    ->exists();

                if (! $exists) {
                    PuzzleComment::create([
                        'user_id' => $completedAttempt->user_id,
                        'crossword_id' => $completedAttempt->crossword_id,
                        'body' => $this->faker->sentence(),
                        'rating' => $this->faker->randomElement([3, 4, 4, 5, 5, 5]),
                    ]);
                    $created++;
                }
            }

            if ($created > 0) {
                $this->info("Created {$created} comment(s).");
            }
        }

        // Follows
        if (random_int(1, 100) <= 30) {
            $count = random_int(1, 2);
            $created = 0;

            $solverIds = User::where('email', 'like', 'solver%@example.com')->pluck('id')->all();
            $constructorIds = Crossword::where('is_published', true)->distinct()->pluck('user_id')->all();
            $allUserIds = array_merge($solverIds, $constructorIds);

            for ($i = 0; $i < $count; $i++) {
                $followerId = $solverIds[array_rand($solverIds)];
                $followingId = random_int(1, 100) <= 70
                    ? $constructorIds[array_rand($constructorIds)]
                    : $allUserIds[array_rand($allUserIds)];

                if ($followerId === $followingId) {
                    continue;
                }

                $exists = Follow::where('follower_id', $followerId)
                    ->where('following_id', $followingId)
                    ->exists();

                if (! $exists) {
                    Follow::create([
                        'follower_id' => $followerId,
                        'following_id' => $followingId,
                    ]);
                    $created++;
                }
            }

            if ($created > 0) {
                $this->info("Created {$created} follow(s).");
            }
        }

        // Favorite lists
        if (random_int(1, 100) <= 10) {
            $user = User::where('email', 'like', 'solver%@example.com')
                ->inRandomOrder()
                ->first();

            if ($user) {
                $list = FavoriteList::firstOrCreate(
                    ['user_id' => $user->id, 'name' => $this->faker->randomElement(['Best Puzzles', 'Favorites', 'To Revisit', 'Fun Ones', 'Tricky'])],
                );

                $completedIds = PuzzleAttempt::where('user_id', $user->id)
                    ->where('is_completed', true)
                    ->pluck('crossword_id')
                    ->all();

                if (! empty($completedIds)) {
                    $toAdd = array_slice($completedIds, 0, random_int(1, 3));
                    $list->crosswords()->syncWithoutDetaching($toAdd);
                    $this->info('Updated a favorite list.');
                }
            }
        }
    }

    private function grantAchievements(CarbonInterface $now): void
    {
        $created = 0;

        // First solve achievement
        $solversWithCompletions = PuzzleAttempt::where('is_completed', true)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $existingFirstSolve = Achievement::where('type', 'first_solve')->pluck('user_id')->flip()->all();

        foreach ($solversWithCompletions as $userId) {
            if (! isset($existingFirstSolve[$userId])) {
                Achievement::create([
                    'user_id' => $userId,
                    'type' => 'first_solve',
                    'label' => 'First Solve',
                    'description' => 'Completed your first crossword puzzle',
                    'icon' => 'trophy',
                    'earned_at' => $now,
                ]);
                $created++;
            }
        }

        // Puzzle master (10+ completions)
        $prolificSolvers = PuzzleAttempt::where('is_completed', true)
            ->selectRaw('user_id, count(*) as total')
            ->groupBy('user_id')
            ->having('total', '>=', 10)
            ->pluck('user_id');

        $existingMaster = Achievement::where('type', 'puzzle_master')->pluck('user_id')->flip()->all();

        foreach ($prolificSolvers as $userId) {
            if (! isset($existingMaster[$userId])) {
                Achievement::create([
                    'user_id' => $userId,
                    'type' => 'puzzle_master',
                    'label' => 'Puzzle Master',
                    'description' => 'Completed 10 crossword puzzles',
                    'icon' => 'star',
                    'earned_at' => $now,
                ]);
                $created++;
            }
        }

        // Speed demon (solve under 300s)
        $speedSolvers = PuzzleAttempt::where('is_completed', true)
            ->where('solve_time_seconds', '<', 300)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $existingSpeed = Achievement::where('type', 'speed_demon')->pluck('user_id')->flip()->all();

        foreach ($speedSolvers as $userId) {
            if (! isset($existingSpeed[$userId])) {
                Achievement::create([
                    'user_id' => $userId,
                    'type' => 'speed_demon',
                    'label' => 'Speed Demon',
                    'description' => 'Solved a puzzle in under 5 minutes',
                    'icon' => 'bolt',
                    'earned_at' => $now,
                ]);
                $created++;
            }
        }

        // Constructor achievement
        $constructors = Crossword::where('is_published', true)
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $existingConstructor = Achievement::where('type', 'constructor')->pluck('user_id')->flip()->all();

        foreach ($constructors as $userId) {
            if (! isset($existingConstructor[$userId])) {
                Achievement::create([
                    'user_id' => $userId,
                    'type' => 'constructor',
                    'label' => 'Constructor',
                    'description' => 'Published your first crossword puzzle',
                    'icon' => 'pencil-square',
                    'earned_at' => $now,
                ]);
                $created++;
            }
        }

        if ($created > 0) {
            $this->info("Granted {$created} achievement(s).");
        }
    }

    private function maybeCreateContestActivity(CarbonInterface $now): void
    {
        $activeContest = Contest::where('status', 'active')->first();

        if (! $activeContest || random_int(1, 100) > 20) {
            return;
        }

        $solver = User::where('email', 'like', 'solver%@example.com')
            ->whereDoesntHave('contestEntries', fn ($q) => $q->where('contest_id', $activeContest->id))
            ->inRandomOrder()
            ->first();

        if (! $solver) {
            return;
        }

        $puzzleCount = $activeContest->crosswords()->count();
        $puzzlesCompleted = random_int(0, $puzzleCount);

        ContestEntry::create([
            'contest_id' => $activeContest->id,
            'user_id' => $solver->id,
            'registered_at' => $now,
            'total_solve_time_seconds' => $puzzlesCompleted > 0 ? random_int(300, 5400) : null,
            'puzzles_completed' => $puzzlesCompleted,
            'meta_answer' => null,
            'meta_solved' => false,
            'meta_submitted_at' => null,
            'meta_attempts_count' => 0,
            'rank' => null,
        ]);

        $this->info('Added a contest entry.');
    }

    /**
     * Clean a raw xd author string into a human name.
     */
    private function cleanAuthorName(string $raw): string
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
