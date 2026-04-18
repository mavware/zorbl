<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePuzzleCommentRequest;
use App\Http\Resources\Api\V1\PuzzleCommentResource;
use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Notifications\NewPuzzleComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Puzzle Comments
 */
class PuzzleCommentController extends Controller
{
    public function index(Crossword $crossword): AnonymousResourceCollection
    {
        $comments = $crossword->comments()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(15);

        return PuzzleCommentResource::collection($comments);
    }

    public function store(StorePuzzleCommentRequest $request, Crossword $crossword): JsonResponse
    {
        $comment = $crossword->comments()->create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        $crosswordOwner = $crossword->user;

        if ($crosswordOwner && $crosswordOwner->id !== $request->user()->id) {
            $crosswordOwner->notify(new NewPuzzleComment($comment, $request->user()));
        }

        return (new PuzzleCommentResource($comment->load('user:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, PuzzleComment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own comments.');
        }

        $comment->delete();

        return response()->json(null, 204);
    }
}
