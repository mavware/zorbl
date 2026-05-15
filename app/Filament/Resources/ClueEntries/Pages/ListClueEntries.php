<?php

namespace App\Filament\Resources\ClueEntries\Pages;

use App\Filament\Resources\ClueEntries\ClueEntryResource;
use App\Models\ClueEntry;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClueEntries extends ListRecords
{
    protected static string $resource = ClueEntryResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ClueEntry::STATUS_PENDING))
                ->badge(ClueEntry::pending()->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ClueEntry::STATUS_APPROVED)),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
