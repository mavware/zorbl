<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTokenRequest;
use App\Http\Resources\Api\V1\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @tags Authentication
 */
class AuthController extends Controller
{
    public function store(StoreTokenRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '401',
                        'title' => 'Unauthorized',
                        'detail' => 'The provided credentials are incorrect.',
                    ],
                ],
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        $token = $user->createToken($request->device_name);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new MeResource($user),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
