<?php

namespace Database\Seeders\Activity;

use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use Carbon\CarbonInterface;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Support\Facades\DB;

class SocialSeeder extends BaseActivitySeeder
{
    protected function runStep(): void
    {
        DB::disableQueryLog();

        $now = now();
        $faker = Faker::create();

        $solverIds = User::where('email', 'like', 'solver%@example.com')->pluck('id')->all();
        $constructorIds = User::where('email', 'like', '%@example.com')
            ->where('email', 'not like', 'solver%@example.com')
            ->pluck('id')
            ->all();
        $allUserIds = array_merge($constructorIds, $solverIds);

        $crosswordIds = Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))
            ->pluck('id')
            ->all();

        if ($allUserIds === [] || $crosswordIds === []) {
            $this->log('Example users or crosswords missing. Run earlier steps first.', 'error');

            return;
        }

        $this->seedComments($now, $faker);
        $this->seedLikes($now, $allUserIds, $crosswordIds);
        $this->seedFollows($now, $solverIds, $constructorIds, $allUserIds);
    }

    /**
     * @param  Generator  $faker
     */
    private function seedComments(CarbonInterface $now, $faker): void
    {
        $completedAttempts = PuzzleAttempt::where('is_completed', true)
            ->whereHas('user', fn ($q) => $q->where('email', 'like', 'solver%@example.com'))
            ->inRandomOrder()
            ->limit(60)
            ->get(['user_id', 'crossword_id', 'completed_at']);

        $existing = PuzzleComment::whereIn('user_id', $completedAttempts->pluck('user_id'))
            ->whereIn('crossword_id', $completedAttempts->pluck('crossword_id'))
            ->select('user_id', 'crossword_id')
            ->get()
            ->mapWithKeys(fn ($c) => ["{$c->user_id}-{$c->crossword_id}" => true])
            ->all();

        $batch = [];
        $target = min(30, $completedAttempts->count());

        foreach ($completedAttempts as $attempt) {
            if (count($batch) >= $target) {
                break;
            }

            $key = "{$attempt->user_id}-{$attempt->crossword_id}";

            if (isset($existing[$key])) {
                continue;
            }

            $existing[$key] = true;
            $batch[] = [
                'user_id' => $attempt->user_id,
                'crossword_id' => $attempt->crossword_id,
                'body' => $faker->sentence(),
                'rating' => $faker->randomElement([3, 4, 4, 5, 5, 5, 3, 4, 2, 5]),
                'created_at' => $attempt->completed_at ?? $now,
                'updated_at' => $now,
            ];
        }

        if ($batch !== []) {
            PuzzleComment::insert($batch);
        }

        $this->log('Created '.count($batch).' comment(s).');
    }

    /**
     * @param  array<int, int>  $allUserIds
     * @param  array<int, int>  $crosswordIds
     */
    private function seedLikes(CarbonInterface $now, array $allUserIds, array $crosswordIds): void
    {
        $batch = [];
        $keys = [];

        $existing = CrosswordLike::whereIn('user_id', $allUserIds)
            ->whereIn('crossword_id', $crosswordIds)
            ->select('user_id', 'crossword_id')
            ->get()
            ->mapWithKeys(fn ($l) => ["{$l->user_id}-{$l->crossword_id}" => true])
            ->all();

        for ($i = 0; $i < 50; $i++) {
            $userId = $allUserIds[array_rand($allUserIds)];
            $crosswordId = $crosswordIds[array_rand($crosswordIds)];
            $key = "{$userId}-{$crosswordId}";

            if (isset($existing[$key]) || isset($keys[$key])) {
                continue;
            }

            $keys[$key] = true;
            $batch[] = [
                'user_id' => $userId,
                'crossword_id' => $crosswordId,
                'created_at' => $now->copy()->subHours(random_int(1, 500)),
                'updated_at' => $now,
            ];
        }

        if ($batch !== []) {
            CrosswordLike::insert($batch);
        }

        $this->log('Created '.count($batch).' like(s).');
    }

    /**
     * @param  array<int, int>  $solverIds
     * @param  array<int, int>  $constructorIds
     * @param  array<int, int>  $allUserIds
     */
    private function seedFollows(CarbonInterface $now, array $solverIds, array $constructorIds, array $allUserIds): void
    {
        if ($solverIds === [] || $constructorIds === []) {
            return;
        }

        $existing = Follow::whereIn('follower_id', $solverIds)
            ->select('follower_id', 'following_id')
            ->get()
            ->mapWithKeys(fn ($f) => ["{$f->follower_id}-{$f->following_id}" => true])
            ->all();

        $batch = [];
        $keys = [];

        for ($i = 0; $i < 30; $i++) {
            $followerId = $solverIds[array_rand($solverIds)];
            $followingId = random_int(1, 100) <= 70
                ? $constructorIds[array_rand($constructorIds)]
                : $allUserIds[array_rand($allUserIds)];

            if ($followerId === $followingId) {
                continue;
            }

            $key = "{$followerId}-{$followingId}";

            if (isset($existing[$key]) || isset($keys[$key])) {
                continue;
            }

            $keys[$key] = true;
            $batch[] = [
                'follower_id' => $followerId,
                'following_id' => $followingId,
                'created_at' => $now->copy()->subDays(random_int(1, 30)),
                'updated_at' => $now,
            ];
        }

        if ($batch !== []) {
            Follow::insert($batch);
        }

        $this->log('Created '.count($batch).' follow(s).');
    }
}
