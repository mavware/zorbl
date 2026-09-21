<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The single source of truth for the app's navigation links. Both the sidebar
 * and the top bar render from these lists, so switching chrome never changes
 * where a user can go.
 */
class AppNavigation
{
    public function __construct(private Request $request) {}

    /**
     * The main destinations, grouped: the user's own work first, then the
     * shared libraries. Groups are rendered with a separator between them.
     *
     * @return array<int, array<int, NavigationItem>>
     */
    public function main(User $user): array
    {
        return [
            array_values(array_filter([
                new NavigationItem(
                    label: __('Build'),
                    icon: 'wrench-screwdriver',
                    href: route('crosswords.index'),
                    current: $this->request->routeIs('crosswords.index', 'crosswords.editor'),
                ),
                new NavigationItem(
                    label: __('Solve'),
                    icon: 'play',
                    href: route('crosswords.solving'),
                    current: $this->request->routeIs('crosswords.solving', 'crosswords.solver', 'crosswords.stats'),
                ),
                $user->isAnonymous() ? null : new NavigationItem(
                    label: __('Favorites'),
                    icon: 'heart',
                    href: route('favorites.index'),
                    current: $this->request->routeIs('favorites.index'),
                ),
            ])),
            array_values(array_filter([
                new NavigationItem(
                    label: __('Clue Library'),
                    icon: 'book-open',
                    href: route('clues.index'),
                    current: $this->request->routeIs('clues.index'),
                ),
                new NavigationItem(
                    label: __('Word Catalog'),
                    icon: 'language',
                    href: route('words.index'),
                    current: $this->request->routeIs('words.*'),
                ),
                new NavigationItem(
                    label: __('Constructors'),
                    icon: 'users',
                    href: route('constructors.index'),
                    current: $this->request->routeIs('constructors.*'),
                ),
                config('crosswordbuilder.features.contests') ? new NavigationItem(
                    label: __('Contests'),
                    icon: 'trophy',
                    href: route('contests.index'),
                    current: $this->request->routeIs('contests.*'),
                ) : null,
            ])),
        ];
    }

    /**
     * Help, support, and (for admins) the admin panel.
     *
     * @return array<int, NavigationItem>
     */
    public function secondary(User $user): array
    {
        return array_values(array_filter([
            new NavigationItem(
                label: __('Help Center'),
                icon: 'question-mark-circle',
                href: route('help.index'),
                current: $this->request->routeIs('help.*'),
            ),
            new NavigationItem(
                label: __('Support'),
                icon: 'chat-bubble-left-right',
                href: route('support.index'),
                current: $this->request->routeIs('support.*'),
            ),
            $user->hasRole('Admin') ? new NavigationItem(
                label: __('Admin'),
                icon: 'shield-check',
                href: route('filament.admin.home'),
                navigate: false,
            ) : null,
        ]));
    }

    /**
     * The legal pages linked from the chrome's footer.
     *
     * @return array<int, NavigationItem>
     */
    public function legal(): array
    {
        return [
            new NavigationItem(label: __('Terms'), icon: 'document-text', href: route('legal.terms')),
            new NavigationItem(label: __('Privacy'), icon: 'document-text', href: route('legal.privacy')),
            new NavigationItem(label: __('Cookies'), icon: 'document-text', href: route('legal.cookies')),
            new NavigationItem(label: __('DMCA'), icon: 'document-text', href: route('legal.dmca')),
        ];
    }

    /**
     * Whether the "Support our work" callout is shown: free, registered users only.
     */
    public function showsUpgradeCallout(User $user): bool
    {
        return ! $user->isPro() && ! $user->isAnonymous();
    }
}
