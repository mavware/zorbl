@props(['rank'])

@if($rank <= 3)
    <span @class([
        'font-classical tnum inline-flex size-7 items-center justify-center rounded-full border text-[14px] font-semibold',
        'border-amber-400 text-amber-400' => $rank === 1,
        'border-ink text-ink' => $rank === 2,
        'border-ink-faint text-ink-faint' => $rank === 3,
    ])>{{ $rank }}</span>
@else
    <span class="font-classical text-ink-faint tnum inline-flex size-7 items-center justify-center text-[14px] font-medium">{{ $rank }}</span>
@endif
