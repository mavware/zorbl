<?php

namespace App\Filament\Resources\DailyPuzzles\Pages;

use App\Filament\Resources\DailyPuzzles\DailyPuzzleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyPuzzles extends ListRecords
{
    protected static string $resource = DailyPuzzleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
