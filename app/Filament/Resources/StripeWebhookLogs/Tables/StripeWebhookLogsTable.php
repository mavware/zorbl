<?php

namespace App\Filament\Resources\StripeWebhookLogs\Tables;

use App\Models\StripeWebhookLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class StripeWebhookLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->since()
                    ->tooltip(fn ($record): string => $record->created_at->format('Y-m-d H:i:s'))
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'failed') => 'danger',
                        str_contains($state, 'deleted') => 'warning',
                        str_contains($state, 'succeeded'), str_contains($state, 'created') => 'success',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('stripe_customer_id')
                    ->label('Customer')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stripe_event_id')
                    ->label('Event ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                IconColumn::make('livemode')
                    ->label('Live')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('processed_at')
                    ->label('Processed')
                    ->boolean()
                    ->tooltip(fn ($record): ?string => $record->error),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(fn (): array => StripeWebhookLog::query()
                        ->select('type')
                        ->distinct()
                        ->orderBy('type')
                        ->pluck('type', 'type')
                        ->all()),
                TernaryFilter::make('livemode')
                    ->label('Live mode')
                    ->placeholder('All')
                    ->trueLabel('Live only')
                    ->falseLabel('Test only'),
                Filter::make('has_error')
                    ->label('Only errors')
                    ->query(fn ($query) => $query->whereNotNull('error')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
