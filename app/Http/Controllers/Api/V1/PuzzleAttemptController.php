<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertAttemptRequest;
use App\Http\Resources\Api\V1\PuzzleAttemptResource;
use App\Models\Crossword;
use App\Services\AchievementService;
use App\Services\ContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Puzzle Attempts
 */
class PuzzleAttemptController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $attempts = QueryBuilder::for(
            $request->user()->puzzleAttempts()
        )
            ->allowedFilters('is_completed')
            ->allowedSorts('updated_at', 'created_at')
            ->allowedIncludes('crossword')
            ->paginate(15);

        return PuzzleAttemptResource::collection($attempts);
    }

    public function show(Request $request, Crossword $crossword): PuzzleAttemptResource
    {
        $attempt = $request->user()
            ->puzzleAttempts()
            ->where('crossword_id', $crossword->id)
            ->firstOrFail();

        return new PuzzleAttemptResource($attempt);
    }

    public function upsert(UpsertAttemptRequest $request, Crossword $crossword): PuzzleAttemptResource|JsonResponse
    {
        Gate::authorize('solve', $crossword);

        $user = $request->user();
        $data = $request->validated();

        $existing = $user->puzzleAttempts()
            ->where('crossword_id', $crossword->id)
            ->first();

        $isCreating = $existing === null;

        $attributes = [
            'progress' => $data['progress'],
            'pencil_cells' => $data['pencil_cells'] ?? null,
            'is_completed' => $data['is_completed'] ?? false,
            'solve_time_seconds' => $data['solve_time_seconds'] ?? null,
        ];

        if (($data['is_completed'] ?? false) && (! $existing || ! $existing->is_completed)) {
            $attributes['completed_at'] = now();

            if (! $existing || ! $existing->started_at) {
                $attributes['started_at'] = now();
            }
        }

        $isNewCompletion = ($data['is_completed'] ?? false) && (! $existing || ! $existing->is_completed);

        $attempt = $user->puzzleAttempts()->updateOrCreate(
            ['user_id' => $user->id, 'crossword_id' => $crossword->id],
            $attributes,
        );

        if ($isNewCompletion) {
            app(AchievementService::class)->processSolve($user, $data['solve_time_seconds'] ?? null);

            if ($crossword->contests()->exists()) {
                app(ContestService::class)->processContestSolve($user, $crossword);
            }
        }

        $resource = new PuzzleAttemptResource($attempt);

        return $isCreating
            ? $resource->response()->setStatusCode(201)
            : $resource;
    }
}
