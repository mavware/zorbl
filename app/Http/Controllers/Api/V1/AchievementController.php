<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AchievementResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Achievements
 */
class AchievementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $achievements = $request->user()
            ->achievements()
            ->orderByDesc('earned_at')
            ->get();

        return AchievementResource::collection($achievements);
    }
}
