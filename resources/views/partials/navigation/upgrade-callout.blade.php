{{-- "Support our work" card for free, registered users. When the sidebar is
     collapsed to a rail, the card gives way to a single sparkles icon. --}}
@inject('navigation', 'App\Support\AppNavigation')

@if ($navigation->showsUpgradeCallout(auth()->user()))
    <div class="mb-2 hidden px-2 in-data-flux-sidebar-collapsed-desktop:block">
        <flux:sidebar.item
            :href="route('billing.index')"
            icon="sparkles"
            :tooltip="__('Support our work')"
            data-test="upgrade-callout-collapsed"
            wire:navigate
        >
            {{ __('Support our work') }}
        </flux:sidebar.item>
    </div>

    <a
        href="{{ route('billing.index') }}"
        wire:navigate
        class="mx-7 mb-2 block in-data-flux-sidebar-collapsed-desktop:hidden rounded-lg border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-amber-500/5 p-3 transition hover:border-amber-500/50 hover:from-amber-500/15 hover:to-amber-500/10"
        data-test="upgrade-callout"
    >
        <div class="flex items-center gap-2">
            <flux:icon.sparkles class="size-4 text-amber-500"/>
            <span class="text-sm font-semibold text-zinc-100 dark:text-zinc-100">{{ __('Support our work') }}</span>
        </div>
        <p class="mt-1 text-xs text-zinc-700 dark:text-zinc-400">
            {{ __('Help keep Crossword Builder free, and unlock AI grid fills and clue suggestions.') }}
        </p>
    </a>
@endif
