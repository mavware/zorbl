<?php

namespace App\Filament\Widgets\Pulse;

class SlowQueriesWidget extends PulseCardWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getPulseCard(): string
    {
        return 'pulse.slow-queries';
    }
}
