<?php

namespace App\Filament\Resources\DailyPuzzles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DailyPuzzleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Daily Puzzle')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('date')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(today()),
                        Select::make('crossword_id')
                            ->relationship('crossword', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Puzzle'),
                    ]),
            ]);
    }
}
