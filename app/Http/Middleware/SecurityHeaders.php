<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply baseline browser security headers. Permissive enough not to break
 * Livewire / Alpine / Stripe / fonts.bunny.net, but restrictive enough to be
 * worth turning on. Embed routes (which need to be iframeable by anyone) are
 * exempted from frame restrictions.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isEmbed = $request->is('embed/*') || $request->is('api/embed/*');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $this->permissionsPolicy());

        if (! $isEmbed) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // HSTS only when actually serving HTTPS — turning it on over plain
        // HTTP would lock dev environments out without warning.
        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->csp($isEmbed));
        }

        return $response;
    }

    private function csp(bool $isEmbed): string
    {
        // Livewire + Alpine evaluate expression strings at runtime, so we need
        // 'unsafe-eval' and 'unsafe-inline' for scripts. Stripe.js, our font
        // host, and Stripe iframes are explicitly listed below.
        //
        // When the Vite dev server is running (local `npm run dev`), its origin
        // must be whitelisted so the injected HMR client, styles, and scripts
        // load. In production this returns '' and the CSP stays locked down.
        $vite = $this->viteDevServerOrigin();
        $viteScript = $vite === '' ? '' : ' '.$vite;
        $viteConnect = $vite === '' ? '' : ' '.$vite.' '.$this->viteWebSocketSource();

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com".$viteScript,
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net".$viteScript,
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://api.stripe.com https://*.ingest.sentry.io https://*.ingest.us.sentry.io".$viteConnect,
            "frame-src 'self' https://js.stripe.com https://hooks.stripe.com",
            'frame-ancestors '.($isEmbed ? '*' : "'self'"),
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ];

        return implode('; ', $directives);
    }

    /**
     * The Vite dev server origin (e.g. `https://crosswordbuilder.test:5173`)
     * when it is running locally, or an empty string otherwise. Detected via
     * the `public/hot` file Vite writes while `npm run dev` is active.
     */
    private function viteDevServerOrigin(): string
    {
        if (app()->environment('production')) {
            return '';
        }

        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return '';
        }

        $url = trim((string) file_get_contents($hotFile));
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * The WebSocket source for Vite HMR (`ws(s)://host:port`), or '' when the
     * dev server is not running.
     */
    private function viteWebSocketSource(): string
    {
        $origin = $this->viteDevServerOrigin();

        if ($origin === '') {
            return '';
        }

        return str_starts_with($origin, 'https://')
            ? 'wss://'.substr($origin, strlen('https://'))
            : 'ws://'.substr($origin, strlen('http://'));
    }

    private function permissionsPolicy(): string
    {
        // Explicitly disable features we don't use. If you add features that
        // need any of these (e.g. payment APIs, camera for QR scanning), open
        // them up to self= only.
        return implode(', ', [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=(self)',
            'usb=()',
        ]);
    }
}
