<?php

namespace App\Enums;

enum TemplateGeneratorStyle: string
{
    case Standard = 'standard';
    case Themed = 'themed';
    case Themeless = 'themeless';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Themed => 'Themed',
            self::Themeless => 'Themeless',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'General-purpose grid at the published word count and block density.',
            self::Themed => 'Standard grid with long across slots reserved in symmetric rows for theme entries.',
            self::Themeless => 'Fewer, longer entries and lower block density.',
        };
    }

    /**
     * Smallest square size the style is meaningful for.
     */
    public function minimumSize(): int
    {
        return match ($this) {
            self::Standard => 5,
            self::Themed, self::Themeless => 11,
        };
    }
}
