<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Editor => 'Editor',
        };
    }
}
