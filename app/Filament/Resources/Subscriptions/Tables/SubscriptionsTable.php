<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        'past_due' => 'warning',
                        'canceled', 'incomplete_expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('stripe_price')
                    ->label('Price ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Subscribed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Active'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'trialing' => 'Trialing',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                        'incomplete' => 'Incomplete',
                        'incomplete_expired' => 'Incomplete Expired',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
