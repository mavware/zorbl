<?php

namespace App\Filament\Resources\StripeWebhookLogs\Pages;

use App\Filament\Resources\StripeWebhookLogs\StripeWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListStripeWebhookLogs extends ListRecords
{
    protected static string $resource = StripeWebhookLogResource::class;
}
