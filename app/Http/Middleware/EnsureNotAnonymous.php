<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotAnonymous
{
    /**
     * Reject anonymous guests from surfaces reserved for registered users.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'isAnonymous') && $user->isAnonymous()) {
            if ($request->expectsJson()) {
                abort(403, 'Sign up to access this feature.');
            }

            return redirect()->route('register')->with(
                'status',
                __('Create a free account to keep building.')
            );
        }

        return $next($request);
    }
}
