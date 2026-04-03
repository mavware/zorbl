<?php

namespace App\Filament\Resources\RoadmapItems\Pages;

use App\Filament\Resources\RoadmapItems\RoadmapItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoadmapItems extends ListRecords
{
    protected static string $resource = RoadmapItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
