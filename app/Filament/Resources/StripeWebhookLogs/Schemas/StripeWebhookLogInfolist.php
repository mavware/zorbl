<?php

namespace App\Filament\Resources\StripeWebhookLogs\Schemas;

use App\Models\StripeWebhookLog;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StripeWebhookLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('stripe_event_id')
                            ->label('Event ID')
                            ->copyable(),
                        TextEntry::make('type')
                            ->badge(),
                        TextEntry::make('created_at')
                            ->label('Received')
                            ->dateTime(),
                        TextEntry::make('processed_at')
                            ->label('Processed')
                            ->dateTime()
                            ->placeholder('Not processed'),
                        IconEntry::make('livemode')
                            ->label('Live mode')
                            ->boolean(),
                        TextEntry::make('error')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('—'),
                        TextEntry::make('user.email')
                            ->label('Email')
                            ->placeholder('—'),
                        TextEntry::make('stripe_customer_id')
                            ->label('Stripe customer ID')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Payload')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('payload')
                            ->hiddenLabel()
                            ->state(fn (StripeWebhookLog $record): string => json_encode(
                                $record->payload,
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                            ) ?: '')
                            ->copyable()
                            ->html()
                            ->formatStateUsing(fn (string $state): string => '<pre class="text-xs whitespace-pre-wrap font-mono">'
                                .e($state).'</pre>'),
                    ]),
            ]);
    }
}
