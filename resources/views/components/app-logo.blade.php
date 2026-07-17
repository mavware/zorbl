@props([
    'sidebar' => false,
    'name' => false,
    // 'name' => config('app.name'),
])

@if($sidebar)
    <flux:sidebar.brand name="{{ $name }}" {{ $attributes->merge(['class' => 'flex-1 justify-center']) }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $name }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:brand>
@endif
