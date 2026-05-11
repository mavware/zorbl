<?php

namespace App\Filament\Resources\WordplayEntries\Pages;

use App\Filament\Resources\WordplayEntries\WordplayEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWordplayEntries extends ListRecords
{
    protected static string $resource = WordplayEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
