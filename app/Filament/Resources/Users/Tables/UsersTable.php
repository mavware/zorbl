<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                TextColumn::make('manual_pro_granted_at')
                    ->label('Manual Pro')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('grantPro')
                    ->label(fn (User $record): string => $record->manual_pro_granted_at ? 'Revoke Pro' : 'Grant Pro')
                    ->icon(fn (User $record): Heroicon => $record->manual_pro_granted_at ? Heroicon::OutlinedXCircle : Heroicon::OutlinedSparkles)
                    ->color(fn (User $record): string => $record->manual_pro_granted_at ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->manual_pro_granted_at ? "Revoke Pro from {$record->name}?" : "Grant Pro to {$record->name}?")
                    ->modalDescription(fn (User $record): string => $record->manual_pro_granted_at
                        ? 'This removes the manual Pro grant. They may still have Pro through an active subscription or the Admin role.'
                        : 'This gives the user full Pro-tier access, bypassing Stripe. They will keep Pro until you revoke it.')
                    ->action(function (User $record): void {
                        $granting = $record->manual_pro_granted_at === null;
                        $record->update(['manual_pro_granted_at' => $granting ? now() : null]);

                        Notification::make()
                            ->title($granting ? "Granted Pro to {$record->name}" : "Revoked Pro from {$record->name}")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
