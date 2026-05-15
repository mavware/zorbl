<?php

namespace App\Filament\Resources\ClueEntries;

use App\Filament\Resources\ClueEntries\Pages\ListClueEntries;
use App\Filament\Resources\ClueEntries\Tables\ClueEntriesTable;
use App\Models\ClueEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClueEntryResource extends Resource
{
    protected static ?string $model = ClueEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'answer';

    protected static ?string $modelLabel = 'Clue';

    protected static ?string $pluralModelLabel = 'Clues';

    public static function table(Table $table): Table
    {
        return ClueEntriesTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ClueEntry::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ClueEntry::pending()->exists() ? 'warning' : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClueEntries::route('/'),
        ];
    }
}
