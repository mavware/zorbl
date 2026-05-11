<?php

namespace App\Filament\Resources\WordplayEntries\Schemas;

use App\Enums\WordplayType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WordplayEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('word')
                    ->required()
                    ->disabled()
                    ->dehydrated(false),
                Select::make('type')
                    ->options(collect(WordplayType::cases())
                        ->mapWithKeys(fn (WordplayType $type): array => [$type->value => $type->label()])
                        ->all())
                    ->required()
                    ->disabled()
                    ->dehydrated(false),
                Select::make('status')
                    ->options([
                        'saved' => 'Saved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                Textarea::make('notes_pretty')
                    ->label('Notes')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(4)
                    ->afterStateHydrated(function (Textarea $component, $record): void {
                        if ($record === null) {
                            return;
                        }
                        $component->state($record->type->describeNotes($record->notes ?? []));
                    }),
            ]);
    }
}
