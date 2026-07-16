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
     * Pulse is compiled against Tailwind's blue-tinted "gray" palette, while
     * Filament's default gray is the neutral zinc. Remapping every shade, in
     * both the rgb() and hex forms pulse.css uses, keeps the cards on the
     * same color scheme as the rest of the panel.
     */
    private const GRAY_TO_ZINC = [
        'rgb(249 250 251' => 'rgb(250 250 250', '#f9fafb' => '#fafafa', // 50
        'rgb(243 244 246' => 'rgb(244 244 245', '#f3f4f6' => '#f4f4f5', // 100
        'rgb(229 231 235' => 'rgb(228 228 231', '#e5e7eb' => '#e4e4e7', // 200
        'rgb(209 213 219' => 'rgb(212 212 216', '#d1d5db' => '#d4d4d8', // 300
        'rgb(156 163 175' => 'rgb(161 161 170', '#9ca3af' => '#a1a1aa', // 400
        'rgb(107 114 128' => 'rgb(113 113 122', '#6b7280' => '#71717a', // 500
        'rgb(75 85 99' => 'rgb(82 82 91', '#4b5563' => '#52525b', // 600
        'rgb(55 65 81' => 'rgb(63 63 70', '#374151' => '#3f3f46', // 700
        'rgb(31 41 55' => 'rgb(39 39 42', '#1f2937' => '#27272a', // 800
        'rgb(17 24 39' => 'rgb(24 24 27', '#111827' => '#18181b', // 900
        'rgb(3 7 18' => 'rgb(9 9 11', '#030712' => '#09090b', // 950
    ];

    /**
     * Inline the assets the embedded Pulse cards need. Pulse's stylesheet is
     * wrapped in a CSS @scope rule confining it to this page's widget area,
     * so its Tailwind preflight cannot leak into the panel chrome. Only the
     * chart bundle is included because Pulse::js() also bundles Livewire,
     * which Filament already loads.
     */
    public static function renderAssets(): string
    {
        $css = strtr(
            file_get_contents(base_path('vendor/laravel/pulse/dist/pulse.css')),
            self::GRAY_TO_ZINC,
        );
        $js = file_get_contents(base_path('vendor/laravel/pulse/dist/pulse.js'));

        return '<style>@scope (.fi-page-content) {'.$css.'}</style>'
            .'<script>'.$js.'</script>';
    }
}
