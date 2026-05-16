<?php

namespace App\Filament\Resources\Users\Tables;

use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['roles', 'subscriptions']))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('two_factor_confirmed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('copyright_name')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->separator(','),
                TextColumn::make('subscription_status')
                    ->label('Plan')
                    ->badge()
                    ->state(fn ($record): string => $record->isPro() ? 'Pro' : 'Free')
                    ->color(fn (string $state): string => $state === 'Pro' ? 'success' : 'gray'),
                TextColumn::make('grandfathered_at')
                    ->label('Grandfathered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('manual_pro_started_at')
                    ->label('Manual Pro starts')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('manual_pro_ended_at')
                    ->label('Manual Pro ends')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('grantPro')
                    ->label(fn (User $record): string => $record->hasActiveManualPro() ? 'End Pro' : 'Grant Pro')
                    ->icon(fn (User $record): Heroicon => $record->hasActiveManualPro() ? Heroicon::OutlinedXCircle : Heroicon::OutlinedSparkles)
                    ->color(fn (User $record): string => $record->hasActiveManualPro() ? 'danger' : 'success')
                    ->modalHeading(fn (User $record): string => $record->hasActiveManualPro()
                        ? "End Pro for {$record->name}?"
                        : "Grant Pro to {$record->name}")
                    ->modalDescription(fn (User $record): ?string => $record->hasActiveManualPro()
                        ? 'This ends the manual Pro grant immediately. They may still have Pro through an active subscription or the Admin role.'
                        : null)
                    ->schema(fn (User $record): array => $record->hasActiveManualPro() ? [] : [
                        DateTimePicker::make('manual_pro_ended_at')
                            ->label('Ends at')
                            ->helperText('Leave blank to grant Pro with no expiration.')
                            ->after('now'),
                    ])
                    ->action(function (User $record, array $data): void {
                        if ($record->hasActiveManualPro()) {
                            $record->update(['manual_pro_ended_at' => now()]);

                            Notification::make()
                                ->title("Ended Pro for {$record->name}")
                                ->success()
                                ->send();

                            return;
                        }

                        $record->update([
                            'manual_pro_started_at' => now(),
                            'manual_pro_ended_at' => $data['manual_pro_ended_at'] ?? null,
                        ]);

                        Notification::make()
                            ->title("Granted Pro to {$record->name}")
                            ->success()
                            ->send();
                    }),
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->color('warning')
                    ->visible(fn (User $record): bool => ! $record->is(Auth::user()) && ! $record->hasRole('Admin'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => "Impersonate {$record->name}?")
                    ->modalDescription('You will be logged in as this user. Use the banner at the top of the page to leave impersonation.')
                    ->action(function (User $record) {
                        session()->put(ImpersonationController::SESSION_KEY, Auth::id());
                        Auth::loginUsingId($record->id);

                        return redirect('/');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
