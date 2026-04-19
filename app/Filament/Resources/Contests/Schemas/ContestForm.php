<?php

namespace App\Filament\Resources\Contests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ContestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contest Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set) {
                                if ($state !== null) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Textarea::make('rules')
                            ->columnSpanFull(),
                    ]),

                Section::make('Meta Answer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_answer')
                            ->required()
                            ->password()
                            ->revealable()
                            ->label('Meta Answer'),
                        Textarea::make('meta_hint')
                            ->label('Hint for Solvers'),
                        TextInput::make('max_meta_attempts')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 = unlimited attempts'),
                    ]),

                Section::make('Schedule & Status')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'upcoming' => 'Upcoming',
                                'active' => 'Active',
                                'ended' => 'Ended',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required()
                            ->live(),
                        Toggle::make('is_featured')
                            ->label('Featured Contest'),
                        DateTimePicker::make('publish_at')
                            ->label('Scheduled Publish Date')
                            ->helperText('Leave empty to publish immediately when status changes. When set on a draft, the contest auto-transitions to upcoming at this time.')
                            ->visible(fn (Get $get): bool => $get('status') === 'draft'),
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->required()
                            ->after('starts_at'),
                    ]),

                Section::make('Crosswords')
                    ->schema([
                        Select::make('crosswords')
                            ->relationship('crosswords', 'title')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->label('Contest Puzzles'),
                    ]),
            ]);
    }
}
