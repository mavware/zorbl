{{-- The main destinations as sidebar items: one group per AppNavigation group,
     with an inset separator between groups. Shared by the sidebar chrome and
     the top bar's mobile drawer. Groups are plain divs rather than
     flux:sidebar.group, which Flux hides when the sidebar is collapsed. --}}
@inject('navigation', 'App\Support\AppNavigation')

<flux:sidebar.nav>
    @foreach ($navigation->main(auth()->user()) as $group)
        @unless ($loop->first)
            <div class="px-7 pb-2 in-data-flux-sidebar-collapsed-desktop:px-2">
                <flux:separator class="bg-line" />
            </div>
        @endunless

        <div class="grid mb-2 px-4 in-data-flux-sidebar-collapsed-desktop:px-2" data-sidebar-group>
            @foreach ($group as $item)
                <flux:sidebar.item
                    :icon="$item->icon"
                    :href="$item->href"
                    :current="$item->current"
                    :attributes="$item->attributes()"
                >
                    {{ $item->label }}
                </flux:sidebar.item>
            @endforeach
        </div>
    @endforeach
</flux:sidebar.nav>
