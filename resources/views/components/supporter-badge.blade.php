{{-- Small "Supporter" pill shown next to a user's name when they have an
     active subscription. Pass either the user model or a precomputed
     boolean (for components that only carry the author's name). --}}
@props(['user' => null, 'supporter' => null, 'size' => 'sm'])

@php
    $isSupporter = $supporter ?? ($user?->isSupporter() ?? false);
@endphp

@if($isSupporter)
    <flux:badge
        color="pink"
        variant="pill"
        :size="$size"
        icon="heart"
        title="{{ __('Supporter — helps keep :app free for everyone', ['app' => config('app.name')]) }}"
        {{ $attributes->merge(['class' => 'align-middle', 'data-supporter-badge' => '']) }}
    >{{ __('Supporter') }}</flux:badge>
@endif
