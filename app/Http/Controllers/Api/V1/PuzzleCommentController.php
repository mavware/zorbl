<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePuzzleCommentRequest;
use App\Http\Requests\Api\V1\UpdatePuzzleCommentRequest;
use App\Http\Resources\Api\V1\PuzzleCommentResource;
use App\Jobs\DispatchWebhooks;
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

        DispatchWebhooks::dispatch(WebhookEvent::PuzzleCommented, $crossword->user_id, [
            'puzzle_id' => $crossword->id,
            'puzzle_title' => $crossword->title,
            'commenter_id' => $request->user()->id,
            'commenter_name' => $request->user()->name,
            'comment_body' => $comment->body,
        ]);

        return (new PuzzleCommentResource($comment->load('user:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePuzzleCommentRequest $request, PuzzleComment $comment): PuzzleCommentResource
    {
        $comment->update($request->validated());

        return new PuzzleCommentResource($comment->load('user:id,name'));
    }

    public function destroy(Request $request, PuzzleComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->json(null, 204);
    }
}
