<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContestMetaRequest;
use App\Http\Resources\Api\V1\ContestEntryResource;
use App\Models\Contest;
use App\Services\ContestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Contest Entries
 */
class ContestEntryController extends Controller
{
    public function __construct(public ContestService $contestService) {}

    public function store(Request $request, Contest $contest): JsonResponse
    {
        $this->authorize('register', $contest);

        $entry = $this->contestService->register($request->user(), $contest);

        $wasRecentlyCreated = $entry->wasRecentlyCreated;

        return (new ContestEntryResource($entry))
            ->response()
            ->setStatusCode($wasRecentlyCreated ? 201 : 200);
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

        $correct = $this->contestService->submitMetaAnswer($entry, $request->answer);

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
