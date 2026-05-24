<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extends the framework's RedirectIfAuthenticated so that anonymous (guest-builder)
 * users can still reach the register and login pages to convert their account.
 */
class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (! Auth::guard($guard)->check()) {
                continue;
            }

            $user = Auth::guard($guard)->user();

            if ($user && method_exists($user, 'isAnonymous') && $user->isAnonymous()) {
                continue;
            }

            return redirect($this->redirectTo($request));
        }

        return $next($request);
    }
}
