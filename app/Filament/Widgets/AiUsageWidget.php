<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiUsageWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $startOfMonth = now()->startOfMonth();

        $gridFills = AiUsage::where('type', 'grid_fill')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $clueGenerations = AiUsage::where('type', 'clue_generation')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $topUsers = AiUsage::where('created_at', '>=', $startOfMonth)
            ->selectRaw('user_id, count(*) as usage_count')
            ->groupBy('user_id')
            ->orderByDesc('usage_count')
            ->limit(3)
            ->with('user:id,name')
            ->get()
            ->map(fn ($row) => ($row->user?->name ?? 'Unknown')." ({$row->usage_count})")
            ->join(', ');

        return [
            Stat::make('AI Grid Fills', $gridFills)
                ->description('This month')
                ->color('purple'),
            Stat::make('AI Clue Generations', $clueGenerations)
                ->description('This month')
                ->color('blue'),
            Stat::make('Total AI Calls', $gridFills + $clueGenerations)
                ->description($topUsers ?: 'No usage yet')
                ->color('gray'),
        ];
    }
}
