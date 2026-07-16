<?php

namespace App\Filament\Widgets\Pulse;

class ExceptionsWidget extends PulseCardWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 6];

    public function getPulseCard(): string
    {
        return 'pulse.exceptions';
    }
}
