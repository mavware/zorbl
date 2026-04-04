<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContestMetaRequest;
use App\Http\Resources\Api\V1\ContestEntryResource;
use App\Models\Contest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Contest Entries
 */
class ContestEntryController extends Controller
{
    public function store(Request $request, Contest $contest): JsonResponse
    {
        $this->authorize('register', $contest);

        $entry = $contest->entries()->create([
            'user_id' => $request->user()->id,
            'registered_at' => now(),
        ]);

        return (new ContestEntryResource($entry))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Contest $contest): ContestEntryResource
    {
        $entry = $contest->entries()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return new ContestEntryResource($entry);
    }

    public function submitMeta(StoreContestMetaRequest $request, Contest $contest): JsonResponse
    {
        $this->authorize('submitMeta', $contest);

        $entry = $contest->entries()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $entry->increment('meta_attempts_count');

        $correct = $contest->checkMetaAnswer($request->answer);

        if ($correct) {
            $entry->update([
                'meta_solved' => true,
                'meta_submitted_at' => now(),
            ]);
        }

        $attemptsRemaining = $contest->max_meta_attempts > 0
            ? max(0, $contest->max_meta_attempts - $entry->fresh()->meta_attempts_count)
            : null;

        return response()->json([
            'data' => [
                'type' => 'meta-results',
                'attributes' => [
                    'correct' => $correct,
                    'attempts_remaining' => $attemptsRemaining,
                ],
            ],
        ]);
    }
}
