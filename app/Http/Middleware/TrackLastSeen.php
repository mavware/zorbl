<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    /**
     * Only write once per hour per user so the tracking stays a rounding
     * error against normal traffic.
     */
    public const THROTTLE_MINUTES = 60;

    /**
     * Stamp the authenticated user's last sighting, throttled.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User && $this->isDue($user)) {
            // Bypass timestamps and events: this is bookkeeping, not an edit.
            User::withoutTimestamps(fn () => $user->forceFill(['last_seen_at' => now()])->saveQuietly());
        }

        return $next($request);
    }

    private function isDue(User $user): bool
    {
        return $user->last_seen_at === null
            || $user->last_seen_at->lt(now()->subMinutes(self::THROTTLE_MINUTES));
    }
}
