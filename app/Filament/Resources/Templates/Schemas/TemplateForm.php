<?php

namespace App\Filament\Resources\Templates\Schemas;

use App\Services\GridTemplateProvider;
use Closure;
use Database\Factories\TemplateFactory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

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
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->default(true),
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

                if (! GridTemplateProvider::hasRotationalSymmetry($value, $width, $height)) {
                    $fail(__('Grid must have 180-degree rotational symmetry.'));

                    return;
                }

                if (! GridTemplateProvider::validateMinWordLength($value, $width, $height)) {
                    $fail(__('Grid contains words shorter than the minimum length of 3.'));
                }
            };
        };
    }
}
