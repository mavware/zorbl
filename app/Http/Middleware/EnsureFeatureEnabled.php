<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns 404 for routes belonging to a feature that is switched off in
 * config/crosswordbuilder.php. Usage: ->middleware('feature:contests').
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(config("crosswordbuilder.features.{$feature}", false), 404);

        return $next($request);
    }
}
