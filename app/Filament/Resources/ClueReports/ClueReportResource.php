<?php

namespace App\Filament\Resources\ClueReports;

use App\Filament\Resources\ClueReports\Pages\CreateClueReport;
use App\Filament\Resources\ClueReports\Pages\EditClueReport;
use App\Filament\Resources\ClueReports\Pages\ListClueReports;
use App\Filament\Resources\ClueReports\Schemas\ClueReportForm;
use App\Filament\Resources\ClueReports\Tables\ClueReportsTable;
use App\Models\ClueReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClueReportResource extends Resource
{
    protected static ?string $model = ClueReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'clue_entry_id';

    public static function form(Schema $schema): Schema
    {
        return ClueReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClueReportsTable::configure($table);
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
            'index' => ListClueReports::route('/'),
            'create' => CreateClueReport::route('/create'),
            'edit' => EditClueReport::route('/{record}/edit'),
        ];
    }
}
