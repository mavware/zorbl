{{-- The main destinations as sidebar items: one group per AppNavigation group,
     with an inset separator between groups. Shared by the sidebar chrome and
     the top bar's mobile drawer. --}}
@inject('navigation', 'App\Support\AppNavigation')

<flux:sidebar.nav>
    @foreach ($navigation->main(auth()->user()) as $group)
        @unless ($loop->first)
            <div class="px-7 pb-2">
                <flux:separator class="bg-line" />
            </div>
        @endunless

        <flux:sidebar.group class="grid mb-2 px-4">
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
        </flux:sidebar.group>
    @endforeach
</flux:sidebar.nav>
