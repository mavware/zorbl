@props([
    'sidebar' => false,
    'name' => config('app.name'),
])

@if($sidebar)
    <flux:sidebar.brand name="{{ $name }}" {{ $attributes->merge(['class' => 'flex-1']) }}>
        <x-slot name="logo" class="flex h-8 items-center">
            <x-app-logo-icon class="h-7 w-auto" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $name }}" {{ $attributes }}>
        <x-slot name="logo" class="flex h-8 items-center">
            <x-app-logo-icon class="h-7 w-auto" />
        </x-slot>
    </flux:brand>
@endif
