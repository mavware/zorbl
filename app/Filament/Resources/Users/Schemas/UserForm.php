<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                TextInput::make('copyright_name'),
                Textarea::make('bio')
                    ->maxLength(500)
                    ->columnSpanFull(),
                CheckboxList::make('roles')
                    ->relationship('roles', 'name')
                    ->columns(2)
                    ->columnSpanFull(),
                DateTimePicker::make('manual_pro_started_at')
                    ->label('Manual Pro starts')
                    ->helperText('When the manual Pro grant begins. Leave blank for no manual grant.'),
                DateTimePicker::make('manual_pro_ended_at')
                    ->label('Manual Pro ends')
                    ->helperText('When the manual Pro grant expires. Leave blank for no expiration.')
                    ->after('manual_pro_started_at'),
                Textarea::make('two_factor_secret')
                    ->columnSpanFull(),
                Textarea::make('two_factor_recovery_codes')
                    ->columnSpanFull(),
                DateTimePicker::make('two_factor_confirmed_at'),
            ]);
    }
}
