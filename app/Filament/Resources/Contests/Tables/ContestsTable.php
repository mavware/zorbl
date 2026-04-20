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
                TextColumn::make('publish_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
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
                    self::schedulePublishBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function schedulePublishBulkAction(): BulkAction
    {
        return BulkAction::make('schedulePublish')
            ->label('Schedule Publish')
            ->icon('heroicon-o-clock')
            ->schema([
                DateTimePicker::make('publish_at')
                    ->label('Publish Date')
                    ->required()
                    ->minDate(now())
                    ->helperText('Selected draft contests will be scheduled to publish at this date and time.'),
            ])
            ->action(function (Collection $records, array $data): void {
                $drafts = $records->where('status', 'draft');

                if ($drafts->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title('No draft contests selected')
                        ->body('Only draft contests can be scheduled for publishing.')
                        ->send();

                    return;
                }

                $drafts->each(fn (Contest $contest) => $contest->update(['publish_at' => $data['publish_at']]));

                $skipped = $records->count() - $drafts->count();

                $notification = Notification::make()
                    ->success()
                    ->title("Scheduled {$drafts->count()} contest(s) for publishing");

                if ($skipped > 0) {
                    $notification->body("{$skipped} non-draft contest(s) were skipped.");
                }

                $notification->send();
            })
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation()
            ->modalHeading('Schedule Publish Date')
            ->modalDescription('Set a publish date for the selected draft contests. Non-draft contests will be skipped.');
    }
}
