<?php

namespace App\Filament\Resources\Crosswords;

use App\Filament\Resources\Crosswords\Pages\ListCrosswords;
use App\Filament\Resources\Crosswords\Tables\CrosswordsTable;
use App\Models\Crossword;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CrosswordResource extends Resource
{
    protected static ?string $model = Crossword::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Puzzles';

    protected static ?int $navigationSort = -1;

    protected static ?string $modelLabel = 'puzzle';

    protected static ?string $slug = 'puzzles';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CrosswordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrosswords::route('/'),
        ];
    }
}
