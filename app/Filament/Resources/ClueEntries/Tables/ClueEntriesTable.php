<?php

namespace App\Filament\Resources\ClueEntries\Tables;

use App\Models\ClueEntry;
use App\Services\ClueQualityChecker;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ClueEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('answer')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),
                TextColumn::make('clue')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('quality_issues')
                    ->label('Quality')
                    ->state(fn (ClueEntry $record): ?string => $record->quality_issues === null ? null : count($record->quality_issues).' '.str('issue')->plural(count($record->quality_issues)))
                    ->placeholder('OK')
                    ->badge()
                    ->color(fn (ClueEntry $record): string => collect($record->quality_issues)->contains('severity', ClueQualityChecker::SEVERITY_ERROR) ? 'danger' : 'warning')
                    ->tooltip(fn (ClueEntry $record): ?string => $record->quality_issues === null ? null : $record->qualityIssuesSummary()),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('crossword.title')
                    ->label('Source')
                    ->placeholder('Standalone')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ClueEntry::STATUS_PENDING => 'warning',
                        ClueEntry::STATUS_APPROVED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ClueEntry::STATUS_PENDING => 'Pending',
                        ClueEntry::STATUS_APPROVED => 'Approved',
                    ]),
                Filter::make('has_quality_issues')
                    ->label('Has quality issues')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('quality_issues')),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::Check)
                    ->color('success')
                    ->visible(fn (ClueEntry $record) => $record->status === ClueEntry::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (ClueEntry $record) => $record->update([
                        'status' => ClueEntry::STATUS_APPROVED,
                        'reviewed_by' => Auth::id(),
                        'reviewed_at' => now(),
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->icon(Heroicon::Check)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'status' => ClueEntry::STATUS_APPROVED,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
