<?php

namespace App\Filament\Resources\DailyPuzzles\Pages;

use App\Filament\Resources\DailyPuzzles\DailyPuzzleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyPuzzle extends EditRecord
{
    protected static string $resource = DailyPuzzleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
