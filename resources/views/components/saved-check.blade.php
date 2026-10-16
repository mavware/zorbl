{{-- Green check that flashes over a settings control once the editor has
     persisted that field (crosswordGrid's flashSettingSaved, driven by the
     component's `setting-saved` event). It sits at the control's end edge,
     exactly where Flux puts the live-update spinner, so the check appears
     where the spinner just stopped. Place it inside a `relative` wrapper
     around the control; `align="top"` pins it to the first line of a
     textarea instead of centring it. --}}
@props(['property', 'align' => 'center'])
<span
    x-show="isSettingFlashing(@js($property))"
    x-cloak
    x-transition.opacity.duration.200ms
    @class([
        'pointer-events-none absolute end-0 flex items-center pe-2 text-emerald-500',
        'inset-y-0' => $align === 'center',
        'top-2' => $align === 'top',
    ])
    data-test="saved-check"
    data-property="{{ $property }}"
    aria-hidden="true"
>
    <flux:icon.check variant="mini" class="size-5" />
</span>
