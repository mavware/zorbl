<?php

namespace App\Filament\Resources\Contests\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ContestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'upcoming' => 'info',
                        'active' => 'success',
                        'ended' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('entries_count')
                    ->counts('entries')
                    ->label('Entries')
                    ->sortable(),
                TextColumn::make('crosswords_count')
                    ->counts('crosswords')
                    ->label('Puzzles')
                    ->sortable(),
                TextColumn::make('publish_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'upcoming' => 'Upcoming',
                        'active' => 'Active',
                        'ended' => 'Ended',
                        'archived' => 'Archived',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('schedule_publish')
                        ->label('Schedule Publish')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            DateTimePicker::make('publish_at')
                                ->label('Scheduled Publish Date')
                                ->required()
                                ->minDate(now())
                                ->helperText('Draft contests will auto-transition to upcoming at this time.'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $drafts = $records->where('status', 'draft');

                            if ($drafts->isEmpty()) {
                                Notification::make()
                                    ->title('No draft contests selected')
                                    ->body('Only draft contests can be scheduled for publishing.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $drafts->each(fn ($contest) => $contest->update([
                                'publish_at' => $data['publish_at'],
                            ]));

                            $count = $drafts->count();
                            $skipped = $records->count() - $count;

                            $body = $skipped > 0
                                ? "{$count} draft contest(s) scheduled. {$skipped} non-draft contest(s) skipped."
                                : "{$count} contest(s) scheduled for publishing.";

                            Notification::make()
                                ->title('Publish dates scheduled')
                                ->body($body)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
