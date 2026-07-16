<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Pulse\CacheWidget;
use App\Filament\Widgets\Pulse\ExceptionsWidget;
use App\Filament\Widgets\Pulse\PeriodSelectorWidget;
use App\Filament\Widgets\Pulse\QueuesWidget;
use App\Filament\Widgets\Pulse\ServersWidget;
use App\Filament\Widgets\Pulse\SlowJobsWidget;
use App\Filament\Widgets\Pulse\SlowOutgoingRequestsWidget;
use App\Filament\Widgets\Pulse\SlowQueriesWidget;
use App\Filament\Widgets\Pulse\SlowRequestsWidget;
use App\Filament\Widgets\Pulse\UsageWidget;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class PulseDashboard extends Dashboard
{
    protected static string $routePath = 'pulse';

    protected static ?string $title = 'Pulse';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 1;

    /**
     * @return list<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            PeriodSelectorWidget::class,
            ServersWidget::class,
            UsageWidget::class,
            QueuesWidget::class,
            CacheWidget::class,
            SlowQueriesWidget::class,
            ExceptionsWidget::class,
            SlowRequestsWidget::class,
            SlowJobsWidget::class,
            SlowOutgoingRequestsWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 12;
    }

    /**
     * The id anchors the @scope rule wrapping Pulse's stylesheet.
     */
    public function getWidgetsContentComponent(): Component
    {
        return parent::getWidgetsContentComponent()
            ->extraAttributes(['id' => 'pulse-widgets']);
    }

    /**
     * Inline the assets the embedded Pulse cards need. Pulse's stylesheet is
     * wrapped in a CSS @scope rule so its Tailwind preflight cannot leak into
     * the panel chrome, and only the chart bundle is included because
     * Pulse::js() also bundles Livewire, which Filament already loads.
     */
    public static function renderAssets(): string
    {
        $css = file_get_contents(base_path('vendor/laravel/pulse/dist/pulse.css'));
        $js = file_get_contents(base_path('vendor/laravel/pulse/dist/pulse.js'));

        return '<style>@scope (#pulse-widgets) {'.$css.'}</style>'
            .'<script>'.$js.'</script>';
    }
}
