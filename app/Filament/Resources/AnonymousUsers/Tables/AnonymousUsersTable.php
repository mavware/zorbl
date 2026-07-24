<?php

namespace App\Filament\Resources\AnonymousUsers\Tables;

use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AnonymousUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['crosswords', 'puzzleAttempts']))
            ->defaultSort('anonymous_created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('crosswords_count')
                    ->label('Crosswords')
                    ->sortable(),
                TextColumn::make('puzzle_attempts_count')
                    ->label('Attempts')
                    ->sortable(),
                TextColumn::make('anonymous_created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Last active')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('anonymous_token')
                    ->label('Token')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_crosswords')
                    ->label('Has crosswords')
                    ->query(fn (Builder $query): Builder => $query->has('crosswords')),
            ])
            ->recordActions([
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->color('warning')
                    ->visible(fn (User $record): bool => ! $record->is(Auth::user()))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => "Impersonate {$record->name}?")
                    ->modalDescription('You will be logged in as this guest. Use the banner at the top of the page to leave impersonation.')
                    ->action(function (User $record) {
                        app(ImpersonationController::class)->beginImpersonating(Auth::user(), $record);

                        return redirect('/');
                    }),
            ]);
    }
}
