<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClueEntryResource;
use App\Models\ClueEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Clue Entries
 */
class ClueEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $clueEntries = QueryBuilder::for(ClueEntry::class)
            ->allowedFilters(
                AllowedFilter::partial('answer'),
                AllowedFilter::partial('clue'),
            )
            ->allowedSorts('answer', 'created_at')
            ->allowedIncludes('crossword', 'user')
            ->paginate(20);

        return ClueEntryResource::collection($clueEntries);
    }
}
