<?php

namespace App\Filament\Resources\Contests\Tables;

use App\Models\Contest;
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
                        ->icon('heroicon-o-calendar')
                        ->schema([
                            DateTimePicker::make('publish_at')
                                ->label('Publish Date')
                                ->required()
                                ->native(false)
                                ->minDate(now())
                                ->helperText('Draft contests will auto-transition to upcoming at this time.'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $drafts = $records->where('status', 'draft');

                            if ($drafts->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('No draft contests selected')
                                    ->body('Only draft contests can have a publish date scheduled.')
                                    ->send();

                                return;
                            }

                            $drafts->each(fn (Contest $contest) => $contest->update([
                                'publish_at' => $data['publish_at'],
                            ]));

                            Notification::make()
                                ->success()
                                ->title('Publish date scheduled')
                                ->body("Scheduled {$drafts->count()} contest(s) for publishing.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
