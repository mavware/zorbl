<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class AnonymousUserManager
{
    public const COOKIE_NAME = 'zorbl_anon';

    private const COOKIE_TTL_MINUTES = 60 * 24 * 365;

    /**
     * Return the existing anonymous user matching the request's cookie, or create
     * one and queue the cookie. Idempotent within a single request.
     */
    public function getOrCreateForRequest(Request $request): User
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (is_string($token) && $token !== '') {
            $user = User::query()
                ->where('is_anonymous', true)
                ->where('anonymous_token', $token)
                ->first();

            if ($user) {
                return $user;
            }
        }

        return $this->create();
    }

    /**
     * Create a brand-new anonymous user and queue its cookie on the response.
     */
    public function create(): User
    {
        $token = (string) Str::uuid().Str::random(4);

        $user = User::create([
            'name' => 'Guest',
            'is_anonymous' => true,
            'anonymous_token' => $token,
            'anonymous_created_at' => now(),
        ]);

        Cookie::queue(Cookie::make(
            name: self::COOKIE_NAME,
            value: $token,
            minutes: self::COOKIE_TTL_MINUTES,
            sameSite: 'lax',
        ));

        return $user;
    }

    /**
     * Look up the anonymous user attached to the request, if any. Does not create one.
     */
    public function findForRequest(Request $request): ?User
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return User::query()
            ->where('is_anonymous', true)
            ->where('anonymous_token', $token)
            ->first();
    }

    /**
     * Forget the anonymous cookie on the response.
     */
    public function forgetCookie(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }
}
