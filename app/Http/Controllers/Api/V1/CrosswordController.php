<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrosswordResource;
use App\Models\Crossword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Crosswords
 */
class CrosswordController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Crossword::where('is_published', true)
            ->with(['user:id,name,copyright_name', 'tags:id,name,slug']);

        if ($request->user()) {
            $blockedTagIds = $request->user()->blockedTags()->pluck('tags.id');

            if ($blockedTagIds->isNotEmpty()) {
                $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $blockedTagIds));
            }
        }

        $crosswords = QueryBuilder::for($query)
            ->allowedFilters(
                'difficulty_label',
                'author',
                'kind',
                AllowedFilter::exact('tag', 'tags.slug'),
            )
            ->allowedSorts('created_at', 'title', 'difficulty_score')
            ->allowedIncludes('user')
            ->withCount(['likes', 'attempts', 'comments'])
            ->paginate(15);

        return CrosswordResource::collection($crosswords);
    }

    public function show(Request $request, Crossword $crossword): CrosswordResource
    {
        if (! $crossword->is_published) {
            if (! $request->user() || $request->user()->id !== $crossword->user_id) {
                abort(404);
            }
        }

        $crossword->loadCount(['likes', 'attempts', 'comments']);

        return new CrosswordResource($crossword);
    }
}
