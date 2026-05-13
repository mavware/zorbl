<?php

namespace App\Support;

use App\Models\CookieConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class CookieConsentManager
{
    public const COOKIE_NAME = 'cookie_consent';

    public const VERSION = 'v1';

    /**
     * Countries where prior-consent banner laws apply (EEA + UK + Switzerland).
     * Conservative list — if you operate elsewhere (Brazil, Quebec, etc.) add the
     * code here and the banner will surface there too.
     *
     * @var array<int, string>
     */
    private const REGULATED_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
        'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'GB', 'CH',
    ];

    /**
     * Decide whether to render the banner for this request.
     */
    public function shouldShowBanner(Request $request): bool
    {
        if (! $this->regionRequiresConsent($request)) {
            return false;
        }

        return ! $this->hasRecordedChoice($request);
    }

    /**
     * Is the visitor in a jurisdiction that requires a prior-consent banner?
     */
    public function regionRequiresConsent(Request $request): bool
    {
        $country = $this->resolveCountry($request);

        // If we can't determine the country, default to showing the banner — over-showing
        // is a safer failure mode than under-showing for compliance.
        if ($country === null) {
            return true;
        }

        return in_array($country, self::REGULATED_COUNTRIES, true);
    }

    /**
     * Has the visitor already recorded a choice (auth user record, anon hash record, or cookie)?
     */
    public function hasRecordedChoice(Request $request): bool
    {
        if ($request->cookie(self::COOKIE_NAME) !== null) {
            return true;
        }

        $user = $request->user();
        if ($user !== null) {
            return CookieConsent::query()
                ->where('user_id', $user->getKey())
                ->where('version', self::VERSION)
                ->exists();
        }

        return CookieConsent::query()
            ->where('identifier_hash', $this->anonymousHash($request))
            ->where('version', self::VERSION)
            ->exists();
    }

    /**
     * Record a consent choice for the current visitor (DB + cookie).
     */
    public function recordChoice(Request $request, string $choice): CookieConsent
    {
        $user = $request->user();
        $country = $this->resolveCountry($request);

        $consent = CookieConsent::query()->updateOrCreate(
            $user !== null
                ? ['user_id' => $user->getKey(), 'version' => self::VERSION]
                : ['identifier_hash' => $this->anonymousHash($request), 'version' => self::VERSION],
            ['choice' => $choice, 'region_country' => $country],
        );

        Cookie::queue(
            self::COOKIE_NAME,
            $choice,
            minutes: 60 * 24 * 365,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        );

        return $consent;
    }

    /**
     * Resolve a two-letter ISO country code from common CDN / proxy headers.
     */
    private function resolveCountry(Request $request): ?string
    {
        $candidates = [
            $request->header('CF-IPCountry'),
            $request->header('X-Vercel-IP-Country'),
            $request->header('X-Country-Code'),
            $request->header('X-AppEngine-Country'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $code = strtoupper(trim($candidate));
            if (preg_match('/^[A-Z]{2}$/', $code) && $code !== 'XX') {
                return $code;
            }
        }

        return null;
    }

    /**
     * Identifier for anonymous visitors. Salted with APP_KEY so the same IP/UA
     * across deployments doesn't trivially correlate.
     */
    private function anonymousHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->ip() ?? '',
            (string) $request->userAgent(),
            (string) config('app.key'),
        ]));
    }
}
