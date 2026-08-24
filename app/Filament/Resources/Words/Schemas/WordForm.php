<?php

namespace App\Filament\Resources\Words\Schemas;

use App\Models\Word;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('word')
                    ->required()
                    ->alpha()
                    ->minLength(Word::MIN_LENGTH)
                    ->maxLength(Word::MAX_LENGTH)
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('word', mb_strtoupper($state ?? '')))
                    ->helperText('Letters only. Stored uppercase; the length column is derived automatically.'),
                TextInput::make('score')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(50)
                    ->helperText('Higher scores are preferred by the grid filler and word suggester.'),
            ]);
    }
}
