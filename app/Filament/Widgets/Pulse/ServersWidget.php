<?php

namespace App\Filament\Widgets\Pulse;

class ServersWidget extends PulseCardWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getPulseCard(): string
    {
        return 'pulse.servers';
    }
}
