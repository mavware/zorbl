<?php

namespace App\Filament\Resources\RoadmapItems\Pages;

use App\Filament\Resources\RoadmapItems\RoadmapItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoadmapItem extends EditRecord
{
    protected static string $resource = RoadmapItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
