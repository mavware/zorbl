<x-dynamic-component :component="\App\Enums\Navigation::current()->layoutComponent()" :title="$title ?? null">
    <flux:main>
        @include('partials.guest-banner')

        {{ $slot }}
    </flux:main>
</x-dynamic-component>
