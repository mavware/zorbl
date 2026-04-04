<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Crossword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Crossword Likes
 */
class CrosswordLikeController extends Controller
{
    public function store(Request $request, Crossword $crossword): JsonResponse
    {
        $request->user()->crosswordLikes()->firstOrCreate([
            'user_id' => $request->user()->id,
            'crossword_id' => $crossword->id,
        ]);

        return response()->json(null, 201);
    }

    public function destroy(Request $request, Crossword $crossword): JsonResponse
    {
        $request->user()->crosswordLikes()
            ->where('crossword_id', $crossword->id)
            ->delete();

        return response()->json(null, 204);
    }
}
