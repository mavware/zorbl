<?php

namespace App\Filament\Clusters\Users;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * One "Users" sidebar item whose pages, registered and anonymous users, are
 * shown as tabs. The slug keeps the URLs from reading /admin/users/users.
 */
class UsersCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $slug = 'people';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
