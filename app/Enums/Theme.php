<?php

namespace App\Enums;

enum Theme: string
{
    case Classical = 'classical';
    case Modern = 'modern';

    /**
     * The theme the application is configured to render, falling back to
     * Modern when APP_THEME is unset or names a theme that does not exist.
     */
    public static function current(): self
    {
        $configured = config('app.theme');

        return is_string($configured) ? (self::tryFrom($configured) ?? self::Modern) : self::Modern;
    }

    public function label(): string
    {
        return match ($this) {
            self::Classical => 'Classical',
            self::Modern => 'Modern',
        };
    }

    /**
     * The static SVG mark used where CSS cannot reach: the favicon. The in-page
     * logo is inline SVG driven by the --logo-* tokens instead.
     */
    public function logoFile(): string
    {
        return match ($this) {
            self::Classical => 'logo.svg',
            self::Modern => 'logo-modern.svg',
        };
    }
}
