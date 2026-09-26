<?php

namespace App\Filament\Resources\ClueEntries\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClueEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('answer')
                    ->required()
                    ->minLength(2)
                    ->maxLength(50)
                    ->regex('/^\s*[A-Za-z]+\s*$/')
                    ->validationMessages(['regex' => 'Answer must contain only letters.'])
                    ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state)))
                    ->extraInputAttributes(['class' => 'font-mono uppercase']),
                Textarea::make('clue')
                    ->required()
                    ->minLength(2)
                    ->maxLength(500)
                    ->rows(3)
                    ->dehydrateStateUsing(fn (string $state): string => trim($state)),
            ])
            ->columns(1);
    }
}
