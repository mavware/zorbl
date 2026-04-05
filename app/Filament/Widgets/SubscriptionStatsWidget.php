<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Cashier\Subscription;

class SubscriptionStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeCount = Subscription::where('stripe_status', 'active')->count();

        $newThisMonth = Subscription::where('stripe_status', 'active')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $canceledThisMonth = Subscription::whereNotNull('ends_at')
            ->where('ends_at', '>=', now()->startOfMonth())
            ->count();

        return [
            Stat::make('Active Subscribers', $activeCount)
                ->description($newThisMonth.' new this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('New This Month', $newThisMonth)
                ->description('Since '.now()->startOfMonth()->format('M j'))
                ->color('info'),
            Stat::make('Churned This Month', $canceledThisMonth)
                ->description('Cancellations')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($canceledThisMonth > 0 ? 'danger' : 'success'),
        ];
    }
}
