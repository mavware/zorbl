@props([
    'variant' => 'primary',
    'href' => null,
    'icon' => null,
])

@php
    $classes = 'btn-header '.($variant === 'secondary' ? 'btn-header-secondary' : 'btn-header-primary');
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if($icon)
            <flux:icon :name="$icon" variant="outline" class="size-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->class($classes) }}>
        @if($icon)
            <flux:icon :name="$icon" variant="outline" class="size-4" />
        @endif
        {{ $slot }}
    </button>
@endif
