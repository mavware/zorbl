<?php

namespace App\Filament\Widgets\Pulse;

use Filament\Widgets\Widget;

class PeriodSelectorWidget extends Widget
{
    protected string $view = 'filament.widgets.pulse.period-selector';

    protected int|string|array $columnSpan = 'full';
}
