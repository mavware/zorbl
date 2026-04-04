<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMeRequest;
use App\Http\Resources\Api\V1\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Me
 */
class MeController extends Controller
{
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    public function update(UpdateMeRequest $request): MeResource
    {
        $request->user()->update($request->validated());

        return new MeResource($request->user()->refresh());
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $completedAttempts = $user->puzzleAttempts()->where('is_completed', true);

        return response()->json([
            'data' => [
                'type' => 'user-stats',
                'id' => (string) $user->id,
                'attributes' => [
                    'puzzles_solved' => (clone $completedAttempts)->count(),
                    'puzzles_created' => $user->crosswords()->count(),
                    'average_solve_time' => (clone $completedAttempts)->avg('solve_time_seconds'),
                    'current_streak' => $user->current_streak,
                    'longest_streak' => $user->longest_streak,
                ],
            ],
        ]);
    }
}
