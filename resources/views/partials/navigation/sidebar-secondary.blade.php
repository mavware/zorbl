{{-- Help Center, Support, and (for admins) Admin as sidebar items. --}}
@inject('navigation', 'App\Support\AppNavigation')

<flux:sidebar.nav class="border-t border-line px-4">
    @foreach ($navigation->secondary(auth()->user()) as $item)
        <flux:sidebar.item
            :icon="$item->icon"
            :href="$item->href"
            :current="$item->current"
            :attributes="$item->attributes()"
        >
            {{ $item->label }}
        </flux:sidebar.item>
    @endforeach
</flux:sidebar.nav>
