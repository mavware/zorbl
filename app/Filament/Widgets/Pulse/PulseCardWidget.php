<?php

namespace App\Filament\Widgets\Pulse;

use Filament\Widgets\Widget;

/**
 * Base class for widgets that embed a Laravel Pulse dashboard card.
 *
 * Each concrete widget wraps a single Pulse Livewire card so it can be
 * arranged on a Filament page like any other widget. The Pulse CSS/JS these
 * cards depend on is injected by the PulseDashboard page's render hook.
 */
abstract class PulseCardWidget extends Widget
{
    protected string $view = 'filament.widgets.pulse.card';

    /**
     * The Livewire alias of the Pulse card to render, e.g. "pulse.servers".
     */
    abstract public function getPulseCard(): string;
}
