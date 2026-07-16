<?php

namespace App\Filament\Widgets\Pulse;

class UsageWidget extends PulseCardWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    public function getPulseCard(): string
    {
        return 'pulse.usage';
    }
}
