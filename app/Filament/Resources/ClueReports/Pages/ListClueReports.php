<?php

namespace App\Filament\Resources\ClueReports\Pages;

use App\Filament\Resources\ClueReports\ClueReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClueReports extends ListRecords
{
    protected static string $resource = ClueReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
