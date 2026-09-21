<?php

namespace App\Enums;

enum Navigation: string
{
    case Sidebar = 'sidebar';
    case Header = 'header';

    /**
     * The navigation chrome the app layout renders, falling back to the
     * sidebar when the config is unset or names a layout that does not exist.
     */
    public static function current(): self
    {
        $configured = config('crosswordbuilder.navigation');

        return is_string($configured) ? (self::tryFrom($configured) ?? self::Sidebar) : self::Sidebar;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sidebar => 'Sidebar',
            self::Header => 'Top bar',
        };
    }

    /**
     * The Blade component that wraps every app page for this navigation.
     */
    public function layoutComponent(): string
    {
        return match ($this) {
            self::Sidebar => 'layouts::app.sidebar',
            self::Header => 'layouts::app.header',
        };
    }
}
