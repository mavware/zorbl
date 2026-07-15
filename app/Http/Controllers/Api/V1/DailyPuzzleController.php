<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrosswordResource;
use App\Models\DailyPuzzle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Daily Puzzle
 */
class DailyPuzzleController extends Controller
{
    public function show(): CrosswordResource|JsonResponse
    {
        $crossword = DailyPuzzle::todayOrAuto();

        if (! $crossword) {
            return response()->json(['data' => null], 200);
        }

        $crossword->loadCount(['likes', 'comments']);

        return (new CrosswordResource($crossword))
            ->additional(['meta' => ['date' => today()->toDateString()]]);
    }

    public function status(Request $request): JsonResponse
    {
        $crossword = DailyPuzzle::todayOrAuto();

        if (! $crossword) {
            return response()->json([
                'data' => [
                    'date' => today()->toDateString(),
                    'has_daily_puzzle' => false,
                    'solved' => false,
                    'solve_time_seconds' => null,
                    'solve_time_formatted' => null,
                    'crossword_id' => null,
                ],
            ]);
        }

        $attempt = $request->user()
            ->puzzleAttempts()
            ->where('crossword_id', $crossword->id)
            ->where('is_completed', true)
            ->first();

        return response()->json([
            'data' => [
                'date' => today()->toDateString(),
                'has_daily_puzzle' => true,
                'solved' => $attempt !== null,
                'solve_time_seconds' => $attempt?->solve_time_seconds,
                'solve_time_formatted' => $attempt?->formattedSolveTime(),
                'crossword_id' => $crossword->id,
            ],
        ]);
    }

    public function history(): AnonymousResourceCollection
    {
        $dailyPuzzles = DailyPuzzle::where('date', '<=', today())
            ->with(['crossword' => fn ($q) => $q->with('user:id,name', 'tags:id,name,slug')->withCount(['likes', 'comments'])])
            ->orderByDesc('date')
            ->paginate(15);

        $dates = collect($dailyPuzzles->items())->map(fn (DailyPuzzle $dp) => [
            'crossword_id' => $dp->crossword_id,
            'date' => $dp->date->toDateString(),
        ])->values()->all();

        $crosswords = $dailyPuzzles->through(fn (DailyPuzzle $dp) => $dp->crossword);

        return CrosswordResource::collection($crosswords)
            ->additional(['meta' => ['dates' => $dates]]);
    }
}
