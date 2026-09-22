<x-dynamic-component :component="\App\Enums\Navigation::current()->layoutComponent()" :title="$title ?? null">
    {{-- Centered and capped so pages stop stretching on wide screens. --}}
    <flux:main container class="max-w-7xl">
        @include('partials.guest-banner')

        {{ $slot }}
    </flux:main>
</x-dynamic-component>
