<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispatches the right named rate-limiter to each Fortify auth route. Login
 * + 2FA keep Fortify's own per-credential limiters via the `limiters` config;
 * this middleware covers the POST endpoints that Fortify doesn't throttle by
 * default (register, password reset, verification resend).
 */
class ThrottleAuthRoutes
{
    /** @var array<string, string> Route-name → named-rate-limiter map. */
    private const ROUTE_LIMITERS = [
        'register.store' => 'register-attempts',
        'password.email' => 'password-reset-requests',
        'password.update' => 'password-reset-requests',
        'verification.send' => 'verification-resend',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $name = optional($request->route())->getName();
        $limiter = self::ROUTE_LIMITERS[$name] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
