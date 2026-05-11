<?php

namespace App\Filament\Resources\WordplayEntries\Tables;

use App\Enums\WordplayType;
use App\Models\WordplayEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WordplayEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('word')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (WordplayType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('notes_pretty')
                    ->label('Notes')
                    ->state(fn (WordplayEntry $record): string => $record->type->describeNotes($record->notes ?? []))
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'saved' => 'success',
                        'rejected' => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(collect(WordplayType::cases())
                        ->mapWithKeys(fn (WordplayType $type): array => [$type->value => $type->label()])
                        ->all()),
                SelectFilter::make('status')
                    ->options([
                        'saved' => 'Saved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('saved'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
