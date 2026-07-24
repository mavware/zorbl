<?php

namespace App\Filament\Resources\AnonymousUsers\Pages;

use App\Filament\Resources\AnonymousUsers\AnonymousUserResource;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousUsers extends ListRecords
{
    protected static string $resource = AnonymousUserResource::class;
}
