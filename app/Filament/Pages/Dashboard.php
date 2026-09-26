<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Pulse\ExceptionsWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Widgets\Widget;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-home';

    /**
     * @return list<class-string<Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ExceptionsWidget::class,
        ];
    }

    /**
     * Six columns so the exceptions card, which spans six on the Pulse page,
     * fills the full width here.
     *
     * @return int|array<string, ?int>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 6;
    }
}
