<?php

namespace App\Filament\Pages;

use Boquizo\FilamentLogViewer\Pages\ListLogs;

/**
 * The log viewer's list page, moved out of the plugin's own "Logs" sidebar
 * group and into the ungrouped section between Dashboard and Pulse.
 */
class LogViewer extends ListLogs
{
    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }
}
