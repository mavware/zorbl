<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContestEntryResource;
use App\Http\Resources\Api\V1\ContestResource;
use App\Models\Contest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Contests
 */
class ContestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $contests = QueryBuilder::for(Contest::public())
            ->allowedFilters('status', 'is_featured')
            ->allowedSorts('starts_at', 'ends_at', 'created_at')
            ->withCount(['entries', 'crosswords'])
            ->paginate(15);

        return ContestResource::collection($contests);
    }

    public function show(Contest $contest): ContestResource
    {
        $this->authorize('view', $contest);

        $contest->loadCount(['entries', 'crosswords']);

        return new ContestResource($contest);
    }

    public function leaderboard(Contest $contest): AnonymousResourceCollection
    {
        $this->authorize('viewLeaderboard', $contest);

        $entries = $contest->entries()
            ->with('user:id,name')
            ->orderBy('rank')
            ->paginate(50);

        return ContestEntryResource::collection($entries);
    }
}
