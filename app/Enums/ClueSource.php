<?php

namespace App\Enums;

enum ClueSource: string
{
    case Ai = 'ai';
    case Wiktionary = 'wiktionary';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI (Claude)',
            self::Wiktionary => 'Wiktionary definitions',
        };
    }
}
