<?php

namespace App\Filament\Resources\ContentReports;

use App\Filament\Resources\ContentReports\Pages\EditContentReport;
use App\Filament\Resources\ContentReports\Pages\ListContentReports;
use App\Filament\Resources\ContentReports\Schemas\ContentReportForm;
use App\Filament\Resources\ContentReports\Tables\ContentReportsTable;
use App\Models\ContentReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContentReportResource extends Resource
{
    protected static ?string $model = ContentReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $pluralLabel = 'Content reports';

    public static function form(Schema $schema): Schema
    {
        return ContentReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentReports::route('/'),
            'edit' => EditContentReport::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ContentReport::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
