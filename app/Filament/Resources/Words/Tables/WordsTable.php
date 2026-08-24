<?php

namespace App\Filament\Resources\Words\Tables;

use App\Models\Word;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('word')
            ->columns([
                TextColumn::make('word')
                    ->sortable()
                    ->searchable(query: self::prefixSearch(...)),
                TextColumn::make('length')
                    ->sortable(),
                TextColumn::make('score')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('clue_entries_count')
                    ->counts('clueEntries')
                    ->label('Clues')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('length')
                    ->options(array_combine(
                        range(Word::MIN_LENGTH, Word::MAX_LENGTH),
                        range(Word::MIN_LENGTH, Word::MAX_LENGTH),
                    )),
                Filter::make('score')
                    ->schema([
                        TextInput::make('min_score')
                            ->label('Minimum score')
                            ->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['min_score'] !== null && $data['min_score'] !== '',
                        fn (Builder $query) => $query->where('score', '>=', (float) $data['min_score']),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Match the search term as a prefix so the unique index on `word` is usable —
     * the catalog can hold hundreds of thousands of rows, where a leading
     * wildcard would force a full scan.
     */
    private static function prefixSearch(Builder $query, string $search): Builder
    {
        $term = preg_replace('/[^A-Z]/', '', mb_strtoupper($search));

        if ($term === '') {
            return $query;
        }

        return $query->where('word', 'like', $term.'%');
    }
}
