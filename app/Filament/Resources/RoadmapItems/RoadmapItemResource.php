<?php

namespace App\Filament\Resources\RoadmapItems;

use App\Filament\Resources\RoadmapItems\Pages\CreateRoadmapItem;
use App\Filament\Resources\RoadmapItems\Pages\EditRoadmapItem;
use App\Filament\Resources\RoadmapItems\Pages\ListRoadmapItems;
use App\Filament\Resources\RoadmapItems\Schemas\RoadmapItemForm;
use App\Filament\Resources\RoadmapItems\Tables\RoadmapItemsTable;
use App\Models\RoadmapItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoadmapItemResource extends Resource
{
    protected static ?string $model = RoadmapItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return RoadmapItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoadmapItemsTable::configure($table);
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
            'index' => ListRoadmapItems::route('/'),
            'create' => CreateRoadmapItem::route('/create'),
            'edit' => EditRoadmapItem::route('/{record}/edit'),
        ];
    }
}
