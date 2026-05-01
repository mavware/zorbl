<?php

namespace App\Filament\Resources\DailyPuzzles\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DailyPuzzlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('crossword.title')
                    ->label('Puzzle')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('crossword.width')
                    ->label('Size')
                    ->state(fn ($record): string => $record->crossword->width.'x'.$record->crossword->height),
                TextColumn::make('selector.name')
                    ->label('Selected By')
                    ->placeholder('Auto'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
