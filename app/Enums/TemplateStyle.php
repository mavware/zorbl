<?php

namespace App\Enums;

enum TemplateStyle: string
{
    case WideOpen = 'wide-open';
    case Blocky = 'blocky';
    case TripleStack = 'triple-stack';
    case RotationalSymmetric = 'rotational-symmetric';
    case Asymmetric = 'asymmetric';
    case ThemelessFriendly = 'themeless-friendly';
    case ThemedFriendly = 'themed-friendly';
    case MiniStyle = 'mini-style';
    case OpenCorners = 'open-corners';
    case ClosedCorners = 'closed-corners';
    case BarGrid = 'bar-grid';
    case RelaxedRules = 'relaxed-rules';

    public function label(): string
    {
        return match ($this) {
            self::WideOpen => 'Wide Open',
            self::Blocky => 'Blocky',
            self::TripleStack => 'Triple Stack',
            self::RotationalSymmetric => 'Rotational Symmetric',
            self::Asymmetric => 'Asymmetric',
            self::ThemelessFriendly => 'Themeless Friendly',
            self::ThemedFriendly => 'Themed Friendly',
            self::MiniStyle => 'Mini Style',
            self::OpenCorners => 'Open Corners',
            self::ClosedCorners => 'Closed Corners',
            self::BarGrid => 'Bar Grid',
            self::RelaxedRules => 'Relaxed Rules',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WideOpen => 'Low block density with generous 6+ letter answers throughout.',
            self::Blocky => 'High block density dominated by short fill.',
            self::TripleStack => 'Three or more adjacent long horizontal entries.',
            self::RotationalSymmetric => 'Standard 180-degree rotational symmetry.',
            self::Asymmetric => 'No symmetry constraint.',
            self::ThemelessFriendly => 'Open layout with no preset theme-entry slots.',
            self::ThemedFriendly => 'Clear symmetric slots reserved for theme entries.',
            self::MiniStyle => 'Small grid with informal mini-puzzle conventions.',
            self::OpenCorners => 'Corners reachable via 6+ letter entries.',
            self::ClosedCorners => 'Corners stair-stepped or isolated from the body.',
            self::BarGrid => 'Uses cell-edge bars instead of black squares.',
            self::RelaxedRules => 'Allows 2-letter words, partial checking, or other relaxations.',
        };
    }
}
