<?php

namespace App\Filament\Resources\ClueReports\Pages;

use App\Filament\Resources\ClueReports\ClueReportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClueReport extends EditRecord
{
    protected static string $resource = ClueReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
