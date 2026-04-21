<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';

    protected static string|null|BackedEnum $navigationIcon = 'heroicon-o-home';
}
