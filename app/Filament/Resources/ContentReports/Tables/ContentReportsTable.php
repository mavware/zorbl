<?php

namespace App\Filament\Resources\ContentReports\Tables;

use App\Models\ContentReport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContentReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Filed')
                    ->since()
                    ->sortable(),
                TextColumn::make('reportable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ContentReport::REPORTABLE_TYPES[$state] ?? class_basename($state))
                    ->sortable(),
                TextColumn::make('reason')
                    ->formatStateUsing(fn (string $state) => ContentReport::REASONS[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'copyright', 'harassment' => 'danger',
                        'inappropriate', 'misinformation' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ContentReport::STATUS_PENDING => 'warning',
                        ContentReport::STATUS_REVIEWING => 'info',
                        ContentReport::STATUS_ACTIONED => 'success',
                        ContentReport::STATUS_DISMISSED => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ContentReport::STATUS_PENDING => 'Pending',
                        ContentReport::STATUS_REVIEWING => 'Reviewing',
                        ContentReport::STATUS_ACTIONED => 'Actioned',
                        ContentReport::STATUS_DISMISSED => 'Dismissed',
                    ]),
                SelectFilter::make('reportable_type')
                    ->label('Type')
                    ->options(array_combine(
                        array_keys(ContentReport::REPORTABLE_TYPES),
                        ContentReport::REPORTABLE_TYPES,
                    )),
                SelectFilter::make('reason')->options(ContentReport::REASONS),
            ])
            ->recordActions([
                EditAction::make()->label('Review'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
