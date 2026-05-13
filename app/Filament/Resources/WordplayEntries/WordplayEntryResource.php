<?php

namespace App\Filament\Resources\WordplayEntries;

use App\Filament\Resources\WordplayEntries\Pages\EditWordplayEntry;
use App\Filament\Resources\WordplayEntries\Pages\ListWordplayEntries;
use App\Filament\Resources\WordplayEntries\Schemas\WordplayEntryForm;
use App\Filament\Resources\WordplayEntries\Tables\WordplayEntriesTable;
use App\Models\WordplayEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WordplayEntryResource extends Resource
{
    protected static ?string $model = WordplayEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Wordplay';

    protected static ?string $recordTitleAttribute = 'word';

    protected static ?string $navigationLabel = 'Saved entries';

    public static function form(Schema $schema): Schema
    {
        return WordplayEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WordplayEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWordplayEntries::route('/'),
            'edit' => EditWordplayEntry::route('/{record}/edit'),
        ];
    }
}
