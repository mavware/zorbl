<?php

namespace App\Filament\Resources\DailyPuzzles\Pages;

use App\Filament\Resources\DailyPuzzles\DailyPuzzleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDailyPuzzle extends CreateRecord
{
    protected static string $resource = DailyPuzzleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['selected_by'] = Auth::id();

        return $data;
    }
}
