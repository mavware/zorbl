<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFavoriteListRequest;
use App\Http\Resources\Api\V1\FavoriteListResource;
use App\Models\Crossword;
use App\Models\FavoriteList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Favorite Lists
 */
class FavoriteListController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $lists = $request->user()
            ->favoriteLists()
            ->withCount('crosswords')
            ->get();

        return FavoriteListResource::collection($lists);
    }

    public function store(StoreFavoriteListRequest $request): JsonResponse
    {
        $list = $request->user()->favoriteLists()->create($request->validated());

        return (new FavoriteListResource($list))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, FavoriteList $favoriteList): JsonResponse
    {
        $this->authorize('delete', $favoriteList);

        $favoriteList->delete();

        return response()->json(null, 204);
    }

    public function addCrossword(Request $request, FavoriteList $favoriteList): JsonResponse
    {
        $this->authorize('update', $favoriteList);

        $request->validate(['crossword' => ['required', 'exists:crosswords,id']]);

        $favoriteList->crosswords()->syncWithoutDetaching([$request->input('crossword')]);

        return response()->json(null, 200);
    }

    public function removeCrossword(Request $request, FavoriteList $favoriteList, Crossword $crossword): JsonResponse
    {
        $this->authorize('update', $favoriteList);

        $favoriteList->crosswords()->detach($crossword->id);

        return response()->json(null, 204);
    }
}
