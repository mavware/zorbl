<?php

namespace App\Filament\Clusters\Users;

use App\Filament\Resources\AnonymousUsers\AnonymousUserResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
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

    /**
     * Tabs are rendered in this order; discovery order would put the
     * anonymous users tab first alphabetically.
     *
     * @return array<class-string>
     */
    public static function getClusteredComponents(): array
    {
        return [
            UserResource::class,
            AnonymousUserResource::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = User::query()
            ->where('is_anonymous', false)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New registered users in the last 24 hours';
    }
}
