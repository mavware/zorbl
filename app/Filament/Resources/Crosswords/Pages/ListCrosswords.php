<?php

namespace App\Filament\Resources\Crosswords\Pages;

use App\Filament\Resources\Crosswords\CrosswordResource;
use Filament\Resources\Pages\ListRecords;

class ListCrosswords extends ListRecords
{
    protected static string $resource = CrosswordResource::class;
}
