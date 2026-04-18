<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Crossword;
use App\Notifications\CrosswordLiked;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Crossword Likes
 */
class CrosswordLikeController extends Controller
{
    public function store(Request $request, Crossword $crossword): JsonResponse
    {
        $wasCreated = $request->user()->crosswordLikes()->firstOrCreate([
            'user_id' => $request->user()->id,
            'crossword_id' => $crossword->id,
        ])->wasRecentlyCreated;

        if ($wasCreated) {
            $crosswordOwner = $crossword->user;

            if ($crosswordOwner && $crosswordOwner->id !== $request->user()->id) {
                $crosswordOwner->notify(new CrosswordLiked($crossword, $request->user()));
            }
        }

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
