<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrosswordResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Constructors
 */
class ConstructorController extends Controller
{
    public function show(User $user): UserResource
    {
        $user->loadCount([
            'crosswords' => fn ($query) => $query->where('is_published', true),
            'followers',
            'following',
        ]);

        return new UserResource($user);
    }

    public function crosswords(Request $request, User $user): AnonymousResourceCollection
    {
        $crosswords = QueryBuilder::for(
            Crossword::where('user_id', $user->id)
                ->where('is_published', true)
        )
            ->allowedFilters('difficulty_label', 'author', 'kind')
            ->allowedSorts('created_at', 'title', 'difficulty_score')
            ->withCount(['likes', 'attempts', 'comments'])
            ->paginate(15);

        return CrosswordResource::collection($crosswords);
    }
}
