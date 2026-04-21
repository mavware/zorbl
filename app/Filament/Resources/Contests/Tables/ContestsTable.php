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
                    self::clearPublishDateBulkAction(),
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

    private static function clearPublishDateBulkAction(): BulkAction
    {
        return BulkAction::make('clearPublishDate')
            ->label('Clear Publish Date')
            ->icon('heroicon-o-x-circle')
            ->action(function (Collection $records): void {
                $scheduled = $records->whereNotNull('publish_at');

                if ($scheduled->isEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title('No scheduled contests selected')
                        ->body('None of the selected contests have a publish date to clear.')
                        ->send();

                    return;
                }

                $scheduled->each(fn (Contest $contest) => $contest->update(['publish_at' => null]));

                $skipped = $records->count() - $scheduled->count();

                $notification = Notification::make()
                    ->success()
                    ->title("Cleared publish date on {$scheduled->count()} contest(s)");

                if ($skipped > 0) {
                    $notification->body("{$skipped} contest(s) without a publish date were skipped.");
                }

                $notification->send();
            })
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation()
            ->modalHeading('Clear Publish Date')
            ->modalDescription('Remove the scheduled publish date from the selected contests. Contests without a publish date will be skipped.');
    }
}
