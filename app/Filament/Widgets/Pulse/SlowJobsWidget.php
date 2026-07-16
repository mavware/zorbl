<?php

namespace App\Filament\Widgets\Pulse;

class SlowJobsWidget extends PulseCardWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 6];

    public function getPulseCard(): string
    {
        return 'pulse.slow-jobs';
    }
}
