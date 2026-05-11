<?php

namespace App\Filament\Resources\WordplayEntries\Pages;

use App\Filament\Resources\WordplayEntries\WordplayEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWordplayEntry extends EditRecord
{
    protected static string $resource = WordplayEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
