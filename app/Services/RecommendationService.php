<?php

namespace App\Services;

use App\Models\Crossword;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Get personalized puzzle recommendations for a user based on their solve history.
     *
     * @return Collection<int, Crossword>
     */
    public function recommend(User $user, int $limit = 6): Collection
    {
        $profile = $this->buildProfile($user);

        if ($profile === null) {
            return new Collection;
        }

        return Cache::remember(
            "recommendations:{$user->id}",
            300,
            fn () => $this->query($user, $profile, $limit),
        );
    }

    /**
     * Build a preference profile from the user's completed solves and likes.
     *
     * @return array{difficulty: string|null, grid_size: string, tag_ids: list<int>, liked_constructor_ids: list<int>}|null
     */
    public function buildProfile(User $user): ?array
    {
        $completedAttempts = $user->puzzleAttempts()
            ->where('is_completed', true)
            ->count();

        if ($completedAttempts < 3) {
            return null;
        }

        $difficulty = $this->preferredDifficulty($user);
        $gridSize = $this->preferredGridSize($user);
        $tagIds = $this->preferredTagIds($user);
        $likedConstructorIds = $this->likedConstructorIds($user);

        return [
            'difficulty' => $difficulty,
            'grid_size' => $gridSize,
            'tag_ids' => $tagIds,
            'liked_constructor_ids' => $likedConstructorIds,
        ];
    }

    /**
     * @param  array{difficulty: string|null, grid_size: string, tag_ids: list<int>, liked_constructor_ids: list<int>}  $profile
     * @return Collection<int, Crossword>
     */
    private function query(User $user, array $profile, int $limit): Collection
    {
        $attemptedIds = $user->puzzleAttempts()->pluck('crossword_id');
        $blockedTagIds = $user->blockedTags()->pluck('tags.id');

        $query = Crossword::where('crosswords.is_published', true)
            ->where('crosswords.user_id', '!=', $user->id)
            ->safeFor($user)
            ->whereNotIn('crosswords.id', $attemptedIds)
            ->with('user:id,name', 'tags:id,name,slug')
            ->withCount('likes');

        if ($blockedTagIds->isNotEmpty()) {
            $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $blockedTagIds));
        }

        $scoreParts = ['0'];
        $bindings = [];

        if ($profile['difficulty'] !== null) {
            $scoreParts[] = '(CASE WHEN difficulty_label = ? THEN 3 ELSE 0 END)';
            $bindings[] = $profile['difficulty'];
        }

        if (! empty($profile['liked_constructor_ids'])) {
            $placeholders = implode(',', array_fill(0, count($profile['liked_constructor_ids']), '?'));
            $scoreParts[] = "(CASE WHEN user_id IN ({$placeholders}) THEN 2 ELSE 0 END)";
            $bindings = array_merge($bindings, $profile['liked_constructor_ids']);
        }

        $scoreParts[] = '(CASE WHEN cached_completed_count > 0 THEN 1 ELSE 0 END)';

        [$sizeMin, $sizeMax] = $this->gridSizeBounds($profile['grid_size']);
        $scoreParts[] = '(CASE WHEN (width * height) BETWEEN ? AND ? THEN 2 ELSE 0 END)';
        $bindings[] = $sizeMin;
        $bindings[] = $sizeMax;

        $scoreExpr = implode(' + ', $scoreParts);
        $query->selectRaw("crosswords.*, ({$scoreExpr}) as relevance_score", $bindings);

        if (! empty($profile['tag_ids'])) {
            $query->leftJoin('crossword_tag', function ($join) use ($profile) {
                $join->on('crosswords.id', '=', 'crossword_tag.crossword_id')
                    ->whereIn('crossword_tag.tag_id', $profile['tag_ids']);
            })
                ->addSelect(DB::raw('COUNT(crossword_tag.tag_id) as tag_match_count'))
                ->groupBy('crosswords.id');

            $query->orderByDesc('tag_match_count');
        }

        $query->orderByDesc('relevance_score')
            ->orderByDesc('cached_completed_count')
            ->limit($limit);

        return $query->get();
    }

    private function preferredDifficulty(User $user): ?string
    {
        $row = DB::table('puzzle_attempts')
            ->join('crosswords', 'puzzle_attempts.crossword_id', '=', 'crosswords.id')
            ->where('puzzle_attempts.user_id', $user->id)
            ->where('puzzle_attempts.is_completed', true)
            ->whereNotNull('crosswords.difficulty_label')
            ->select('crosswords.difficulty_label', DB::raw('COUNT(*) as cnt'))
            ->groupBy('crosswords.difficulty_label')
            ->orderByDesc('cnt')
            ->first();

        return $row?->difficulty_label;
    }

    private function preferredGridSize(User $user): string
    {
        $avg = DB::table('puzzle_attempts')
            ->join('crosswords', 'puzzle_attempts.crossword_id', '=', 'crosswords.id')
            ->where('puzzle_attempts.user_id', $user->id)
            ->where('puzzle_attempts.is_completed', true)
            ->selectRaw('AVG(crosswords.width * crosswords.height) as avg_cells')
            ->value('avg_cells');

        if ($avg === null || $avg <= 100) {
            return 'small';
        }

        if ($avg <= 289) {
            return 'medium';
        }

        return 'large';
    }

    /**
     * @return list<int>
     */
    private function preferredTagIds(User $user): array
    {
        return DB::table('puzzle_attempts')
            ->join('crossword_tag', 'puzzle_attempts.crossword_id', '=', 'crossword_tag.crossword_id')
            ->where('puzzle_attempts.user_id', $user->id)
            ->where('puzzle_attempts.is_completed', true)
            ->select('crossword_tag.tag_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('crossword_tag.tag_id')
            ->orderByDesc('cnt')
            ->limit(5)
            ->pluck('tag_id')
            ->all();
    }

    /**
     * @return list<int>
     */
    private function likedConstructorIds(User $user): array
    {
        return DB::table('crossword_likes')
            ->join('crosswords', 'crossword_likes.crossword_id', '=', 'crosswords.id')
            ->where('crossword_likes.user_id', $user->id)
            ->select('crosswords.user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('crosswords.user_id')
            ->orderByDesc('cnt')
            ->limit(5)
            ->pluck('user_id')
            ->all();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function gridSizeBounds(string $size): array
    {
        return match ($size) {
            'small' => [0, 100],
            'medium' => [101, 289],
            'large' => [290, 900],
            default => [0, 900],
        };
    }
}
