<?php

namespace App\Filament\Resources\DailyPuzzles;

use App\Filament\Resources\DailyPuzzles\Pages\CreateDailyPuzzle;
use App\Filament\Resources\DailyPuzzles\Pages\EditDailyPuzzle;
use App\Filament\Resources\DailyPuzzles\Pages\ListDailyPuzzles;
use App\Filament\Resources\DailyPuzzles\Schemas\DailyPuzzleForm;
use App\Filament\Resources\DailyPuzzles\Tables\DailyPuzzlesTable;
use App\Models\DailyPuzzle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DailyPuzzleResource extends Resource
{
    protected static ?string $model = DailyPuzzle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'date';

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Daily Puzzles';

    public static function form(Schema $schema): Schema
    {
        return DailyPuzzleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyPuzzlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyPuzzles::route('/'),
            'create' => CreateDailyPuzzle::route('/create'),
            'edit' => EditDailyPuzzle::route('/{record}/edit'),
        ];
    }
}
