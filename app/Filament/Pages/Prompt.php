<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Prompt extends Page
{
    protected string $view = 'filament.pages.prompt';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Sparkles;
}
