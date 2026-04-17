<?php

namespace App\Filament\Resources\StripeWebhookLogs;

use App\Filament\Resources\StripeWebhookLogs\Pages\ListStripeWebhookLogs;
use App\Filament\Resources\StripeWebhookLogs\Pages\ViewStripeWebhookLog;
use App\Filament\Resources\StripeWebhookLogs\Schemas\StripeWebhookLogInfolist;
use App\Filament\Resources\StripeWebhookLogs\Tables\StripeWebhookLogsTable;
use App\Models\StripeWebhookLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StripeWebhookLogResource extends Resource
{
    protected static ?string $model = StripeWebhookLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'stripe_event_id';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return StripeWebhookLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StripeWebhookLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStripeWebhookLogs::route('/'),
            'view' => ViewStripeWebhookLog::route('/{record}'),
        ];
    }
}
