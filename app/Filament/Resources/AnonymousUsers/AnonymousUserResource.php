<?php

namespace App\Filament\Resources\AnonymousUsers;

use App\Filament\Resources\AnonymousUsers\Pages\ListAnonymousUsers;
use App\Filament\Resources\AnonymousUsers\Tables\AnonymousUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnonymousUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Anonymous User';

    protected static ?string $pluralModelLabel = 'Anonymous Users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_anonymous', true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AnonymousUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnonymousUsers::route('/'),
        ];
    }
}
