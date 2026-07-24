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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnonymousUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['crosswords', 'puzzleAttempts'])
                ->addSelect([
                    'last_ip_address' => self::latestSession('ip_address'),
                    'last_user_agent' => self::latestSession('user_agent'),
                    'last_session_activity' => self::latestSession('last_activity'),
                ]))
            ->defaultSort('anonymous_created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('anonymous_token')
                    ->label('Token')
                    ->fontFamily('mono')
                    ->limit(16)
                    ->copyable()
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('last_ip_address')
                    ->label('IP')
                    ->placeholder('—'),
                TextColumn::make('last_user_agent')
                    ->label('User agent')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),
                TextColumn::make('last_session_activity')
                    ->label('Last seen')
                    ->formatStateUsing(fn (?int $state): ?string => $state
                        ? Carbon::createFromTimestamp($state)->diffForHumans()
                        : null)
                    ->tooltip(fn (?int $state): ?string => $state
                        ? Carbon::createFromTimestamp($state)->toDayDateTimeString()
                        : null)
                    ->placeholder('Never'),
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

    /**
     * Correlated subquery selecting the given column from the user's most
     * recent database session. Session data is best-effort and is removed
     * when the session expires.
     */
    private static function latestSession(string $column): \Illuminate\Database\Query\Builder
    {
        return DB::table('sessions')
            ->select($column)
            ->whereColumn('sessions.user_id', 'users.id')
            ->orderByDesc('last_activity')
            ->limit(1);
    }
}
