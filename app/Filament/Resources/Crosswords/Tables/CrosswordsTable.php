<?php

namespace App\Filament\Resources\Crosswords\Tables;

use App\Enums\PuzzleType;
use App\Models\Crossword;
use App\Models\User;
use App\Services\CrosswordPublisher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CrosswordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user.roles', 'user.subscriptions']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->state(fn (Crossword $record): string => $record->displayTitle())
                    ->description(fn (Crossword $record): ?string => filled($record->author) ? "by {$record->author}" : null)
                    ->searchable(['title', 'author'])
                    ->sortable()
                    ->limit(50),
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (Crossword $record): ?string => $record->user?->email)
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user_tier')
                    ->label('User Tier')
                    ->badge()
                    ->state(fn (Crossword $record): ?string => self::userTier($record->user))
                    ->placeholder('—')
                    ->color(fn (string $state): string => match ($state) {
                        'Pro' => 'success',
                        'Guest' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('completeness')
                    ->label('% Complete')
                    ->state(fn (Crossword $record): int => $record->completeness()['percentage'])
                    ->suffix('%')
                    ->badge()
                    ->color(fn (int $state): string => $state === 100 ? 'success' : 'gray')
                    ->alignEnd(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('size')
                    ->state(fn (Crossword $record): string => "{$record->width}×{$record->height}")
                    ->toggleable(),
                TextColumn::make('puzzle_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?PuzzleType $state): string => $state?->label() ?? PuzzleType::Standard->label())
                    ->toggleable(),
                TextColumn::make('difficulty_label')
                    ->label('Difficulty')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Published'),
                SelectFilter::make('puzzle_type')
                    ->label('Type')
                    ->options(collect(PuzzleType::cases())->mapWithKeys(fn (PuzzleType $type): array => [$type->value => $type->label()])->all()),
            ])
            ->recordActions([
                self::togglePublishedAction(),
            ]);
    }

    /**
     * Publish a draft or unpublish a published puzzle, exactly as its
     * constructor would from the editor. An incomplete puzzle can still be
     * published; the confirmation says how complete it is.
     */
    private static function togglePublishedAction(): Action
    {
        return Action::make('togglePublished')
            ->label(fn (Crossword $record): string => $record->is_published ? 'Unpublish' : 'Publish')
            ->icon(fn (Crossword $record): Heroicon => $record->is_published ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedGlobeAlt)
            ->color(fn (Crossword $record): string => $record->is_published ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn (Crossword $record): string => ($record->is_published ? 'Unpublish' : 'Publish')." “{$record->displayTitle()}”?")
            ->modalDescription(function (Crossword $record): string {
                if ($record->is_published) {
                    return 'Solvers will no longer be able to find or open this puzzle, and its clues leave the clue library.';
                }

                $percentage = $record->completeness()['percentage'];

                return $percentage === 100
                    ? 'The puzzle becomes available to solvers and its constructor\'s followers are notified.'
                    : "This puzzle is only {$percentage}% complete. It becomes available to solvers as it is, and its constructor's followers are notified.";
            })
            ->action(function (Crossword $record, CrosswordPublisher $publisher): void {
                if ($record->is_published) {
                    $publisher->unpublish($record);
                } else {
                    $publisher->publish($record);
                }

                Notification::make()
                    ->success()
                    ->title($record->is_published ? 'Puzzle published' : 'Puzzle unpublished')
                    ->send();
            });
    }

    /**
     * The plan the puzzle's owner is on: Guest for an account-less visitor, otherwise Pro or Free.
     */
    private static function userTier(?User $user): ?string
    {
        return match (true) {
            $user === null => null,
            $user->isAnonymous() => 'Guest',
            $user->isPro() => 'Pro',
            default => 'Free',
        };
    }
}
