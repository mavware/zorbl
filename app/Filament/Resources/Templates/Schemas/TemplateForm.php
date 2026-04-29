<?php

namespace App\Filament\Resources\Templates\Schemas;

use App\Services\TemplateStatsService;
use Closure;
use Database\Factories\TemplateFactory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class TemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('width')
                    ->required()
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(27)
                    ->default(15)
                    ->live(onBlur: true),
                TextInput::make('height')
                    ->required()
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(27)
                    ->default(15)
                    ->live(onBlur: true),
                ViewField::make('grid')
                    ->view('filament.components.template-grid-editor')
                    ->columnSpanFull()
                    ->default(fn (Get $get) => TemplateFactory::openGrid(
                        (int) ($get('width') ?: 15),
                        (int) ($get('height') ?: 15),
                    ))
                    ->rules([self::gridRule()])
                    ->required(),
                Hidden::make('styles')
                    ->default(null)
                    ->dehydrateStateUsing(fn ($state) => is_array($state) && count($state) > 0 ? $state : null),
                Section::make('Stats')
                    ->description('Recomputed when width/height change or when the form re-renders. Use the form\'s Save button to commit pending grid edits before relying on these numbers.')
                    ->collapsible()
                    ->schema([
                        Placeholder::make('stats_display')
                            ->hiddenLabel()
                            ->content(fn (Get $get) => self::renderStats($get)),
                    ]),
                TextInput::make('min_word_length')
                    ->label(__('Minimum word length'))
                    ->helperText(__('Shortest allowed run of open cells, enforced when validating the grid.'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
                    ->default(3),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->default(true),
                Section::make('Annotation')
                    ->description('Prose commentary used in the curated training set. Leave blank to skip — the annotation is only saved when philosophy is filled.')
                    ->relationship(
                        'annotation',
                        condition: fn (?array $state): bool => filled($state['philosophy'] ?? null),
                    )
                    ->collapsible()
                    ->schema([
                        Textarea::make('philosophy')
                            ->helperText('1-2 sentences. State the layout\'s intent, not its mechanics.')
                            ->rows(3)
                            ->required(fn (Get $get): bool => filled($get('strengths'))
                                || filled($get('compromises'))
                                || filled($get('best_for'))
                                || filled($get('avoid_when')))
                            ->columnSpanFull(),
                        Repeater::make('strengths')
                            ->helperText('Concrete design wins — specific row counts, entry lengths, structural moves. Aim for 3.')
                            ->simple(
                                Textarea::make('strength')
                                    ->rows(2)
                                    ->required(),
                            )
                            ->defaultItems(0)
                            ->maxItems(5)
                            ->reorderable()
                            ->addActionLabel('Add strength')
                            ->columnSpanFull(),
                        Repeater::make('compromises')
                            ->helperText('Concrete trade-offs the constructor accepts. Aim for 3.')
                            ->simple(
                                Textarea::make('compromise')
                                    ->rows(2)
                                    ->required(),
                            )
                            ->defaultItems(0)
                            ->maxItems(5)
                            ->reorderable()
                            ->addActionLabel('Add compromise')
                            ->columnSpanFull(),
                        Textarea::make('best_for')
                            ->helperText('1 sentence — puzzle type, difficulty target, or construction context.')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('avoid_when')
                            ->helperText('1 sentence — contexts where this template hurts more than it helps.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function gridRule(): Closure
    {
        return function (Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $width = (int) ($get('width') ?: 0);
                $height = (int) ($get('height') ?: 0);

                if (! is_array($value) || count($value) !== $height) {
                    $fail(__('Grid dimensions do not match the specified height.'));

                    return;
                }

                foreach ($value as $row) {
                    if (! is_array($row) || count($row) !== $width) {
                        $fail(__('Grid dimensions do not match the specified width.'));

                        return;
                    }
                }

                // Min-word-length is intentionally NOT enforced here. Instead the Save
                // button surfaces a confirmation modal listing any short runs so the user
                // can save anyway — see WarnsOnShortRuns trait used by the Edit/Create pages.
            };
        };
    }

    private static function renderStats(Get $get): HtmlString|string
    {
        $width = (int) ($get('width') ?: 0);
        $height = (int) ($get('height') ?: 0);
        $grid = $get('grid');
        $styles = $get('styles');

        if ($width < 1 || $height < 1 || ! is_array($grid) || count($grid) !== $height) {
            return __('Stats unavailable until the grid is fully sized.');
        }

        foreach ($grid as $row) {
            if (! is_array($row) || count($row) !== $width) {
                return __('Stats unavailable: grid rows do not match the configured width.');
            }
        }

        try {
            $stats = app(TemplateStatsService::class)->forGrid($grid, $width, $height, is_array($styles) ? $styles : []);
        } catch (\Throwable $e) {
            return __('Stats unavailable: :message', ['message' => $e->getMessage()]);
        }

        return new HtmlString(
            view('filament.components.template-stats', ['stats' => $stats])->render(),
        );
    }
}
