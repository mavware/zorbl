<?php

namespace App\Filament\Resources\HelpArticles\Schemas;

use App\Models\HelpArticle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class HelpArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                                if ($operation === 'create' && $state !== null) {
                                    $set('slug', Str::slug($state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rules(['alpha_dash'])
                            ->columnSpanFull(),
                        Textarea::make('summary')
                            ->maxLength(280)
                            ->rows(2)
                            ->helperText('Shown on the help center index and in social previews.')
                            ->columnSpanFull(),
                        MarkdownEditor::make('body')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Organization & visibility')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('category')
                                ->options(HelpArticle::CATEGORIES)
                                ->required(),
                            TextInput::make('sort_order')
                                ->numeric()
                                ->default(0)
                                ->required(),
                        ]),
                        Grid::make(2)->schema([
                            Toggle::make('is_published')
                                ->label('Published')
                                ->default(true),
                            DateTimePicker::make('published_at')
                                ->helperText('Leave blank to publish immediately.'),
                        ]),
                    ]),
            ]);
    }
}
